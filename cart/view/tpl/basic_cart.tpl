<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>CART CONTENTS</h2>
	</div>
	<div class="section-subtitle-wrapper">
		<h3>{{if $title}}{{$title}}{{else}}Order{{/if}}</h3>
	</div>
	<div class="section-content-wrapper">
		<table class="w-100">
			<tr>
				<th width=60%>Description</th>
				<th width=20% style="text-align:right;">Price each {{if $currencysymbol}}({{$currencysymbol}}){{/if}}</th>
				<th width=20% style="text-align:right;">Extended</th>
			</tr>
			{{foreach $items as $item}}
			<tr>
				<td>{{$item.item_desc}}</td>
				<td style="text-align:right;">{{$item.item_price}}</td>
				<td style="text-align:right;">{{$item.extended}}</td>
			</tr>
			{{/foreach}}
			<tr>
				<td></td>
				<th style="text-align:right;">Subtotal</th>
				<td style="text-align:right;">{{$totals.Subtotal}}</td>
			</tr>
			<tr>
				<td></td>
				<th style="text-align:right;">Tax Total</th>
				<td style="text-align:right;">{{$totals.Tax}}</td>
			</tr>
			<tr>
				<td></td>
				<th style="text-align:right;">Order Total</th>
				<td style="text-align:right;">{{$totals.OrderTotal}}</td>
			</tr>
			{{if $totals.Payment}}
			<tr>
				<td></td>
				<th>Payment</th>
				<td style="text-align:right;">{{$totals.Payment}}</td>
			</tr>
			{{/if}}

			{{**if !$order.checkedout}}
			<tr>
				<td></td>
				<th>Order Not Checked Out</th>
				<td><a href="{{$links.checkoutlink}}">Check Out</a></td>
			</tr>
			{{/if**}}
		</table>
	</div>
	<!-- basic_checkout_*.tpl -->


