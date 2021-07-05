<?php
namespace Haxibiao\Wallet\Traits;

use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Question\Answer;
use Haxibiao\Task\Assignment;
use Haxibiao\Task\Task;
use Haxibiao\Wallet\LuckyDraw;
use Haxibiao\Wallet\Withdraw;
use Illuminate\Support\Facades\Cache;

trait LuckyDrawResolvers
{

    //上期报名抽奖活动用户中奖名单
    public function resolveLuckyDraws($root, $args, $context, ResolveInfo $info)
    {
        app_track_event("任务", "高额抽奖获奖名单");

        return LuckyDraw::where('status', LuckyDraw::STATUS_WIN)->yesterday()->inRandomOrder()->take(60);
    }

    //报名参与抽奖活动
    public function resolveJoinLuckyDraw($root, $args, $context, ResolveInfo $info)
    {
        $user      = getUser();
        $isOldUser = $user->created_at < "2020-12-08";

        // if (!AppHelper::version()->gte('3.6.0')) {
        //     throw new GQLException("请先升级最新版本,再来参加此活动！");
        // }
        // if ($user->name == \App\User::DEFAULT_USER_NAME) {
        //     throw new GQLException("请先更新昵称再来参与活动吧～");
        // }

        //第一次参与不设条件&& 新版本用户
        $luckyDraw = LuckyDraw::where('user_id', $user->id)->orderBy('created_at', 'desc')->first();
        if ($luckyDraw || $isOldUser) {
            //不能重复报名参加
            if ($luckyDraw && $luckyDraw->created_at > today()) {
                throw new UserException("您已报名成功，明天记得登录查看抽奖结果哦~");
            }
            //看有趣小视频任务2次
            //答题20道
            $task = \App\Task::whereName('有趣小视频')->first();
            if (empty($task)) {
                throw new UserException("活动已关闭,具体详情请联系官方客服！");
            }
            $assignment       = Assignment::where('user_id', $user->id)->where('task_id', $task->id)->first();
            $todayAnswerCount = Answer::where('user_id', $user->id)->today()->count();

            if ($todayAnswerCount < LuckyDraw::JOIN_ANSWER_COUNT
                || empty($assignment)
                || $assignment->current_count < LuckyDraw::JOIN_DRAW_COUNT) {
                throw new UserException("需完成今日答20道题,并观看有趣小视频,才可以报名");
            }
        }

        //报名
        $luckyDraw = LuckyDraw::create([
            'user_id' => $user->id,
        ]);
        app_track_event("任务", "报名参与抽奖活动");

        return $luckyDraw;
    }

    //高额抽奖界面 任务进度信息
    public function resolveLuckyDrawInfo($root, $args, $context, ResolveInfo $info)
    {
        $user           = getUser();
        $task           = \App\Task::whereName('有趣小视频')->first();
        $assignment     = Assignment::where('user_id', $user->id)->where('task_id', $task->id)->first();
        $todayTaskCount = $assignment->current_count ?? 0;
        $countdown      = today()->addDay()->diffInSeconds(now()) * 1000; //返回毫秒级时间

        $todayAnswerCount = Answer::where('user_id', $user->id)->today()->count() ?? 0;

        $luckyDraw = LuckyDraw::where('user_id', $user->id)->today()->first(); //是否已报名

        return [
            "countdown" => $countdown,
            "signUp"    => empty($luckyDraw) ? false : true,
            "data"      => [
                ["name" => "每日答题20道", "process" => ($todayAnswerCount > LuckyDraw::JOIN_ANSWER_COUNT ? LuckyDraw::JOIN_ANSWER_COUNT : $todayAnswerCount) . "/" . LuckyDraw::JOIN_ANSWER_COUNT],
                ["name" => "观看有趣小视频2个", "process" => ($todayTaskCount > LuckyDraw::JOIN_DRAW_COUNT ? LuckyDraw::JOIN_DRAW_COUNT : $todayTaskCount) . "/" . LuckyDraw::JOIN_DRAW_COUNT]]];
    }

    //查询用户是否中奖-前端任务弹窗通知用
    public function resolveLuckyUser($root, $args, $context, ResolveInfo $info)
    {
        //提过现就不给弹窗通知了
        $withdraw = Withdraw::query()
            ->where('user_id', $args['user_id'])
            ->luckDrawType()
            ->where('created_at', '>', today())
            ->first();
        if ($withdraw) {
            return null;
        }

        $luckyDraw = LuckyDraw::yesterday()->where('user_id', $args['user_id'])->first();
        if ($luckyDraw) {

            $cache_key = 'luckyUser_show' . $luckyDraw->created_at . $args['user_id'];
            if (!Cache::get($cache_key)) {
                Cache::put($cache_key, 1, today()->addDay()->diffInMinutes(now()));
                return $luckyDraw;
            }
        }
        return null;
    }
}
