<h1>
	正在微信扫码支付...
</h1>
<body>

	<h2>订单号：{{ $order['out_trade_no'] }}</h2>
	<h2>金额：{{ $order['total_fee']/100 }}</h2>
	<h2>备注：{{ $order['body'] }}</h2>
	<div class="container">
		<img style="width:200px; height:200px" src="{{ wechat_pay_code($code_url) }}"/>
	</div>
	{{-- <h3>支付完成后，页面自动刷新返回...</h3> --}}
	<h4><a href="/pay/wechat/return?trade_no={{ $order['out_trade_no'] }}">支付完成后，点这里返回验证支付结果</a></h4>
</body>