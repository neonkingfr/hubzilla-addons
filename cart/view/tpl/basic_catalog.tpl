<div class="generic-content-wrapper dm42cart catalog">
	<div class="section-title-wrapper clearfix">
		{{if $total_qty}}
		<a href="cart/{{$sellernick}}/checkout/start" class="btn btn-sm btn-success float-right"><i class="fa fa-shopping-cart"></i> Checkout ({{$total_qty}})</a>
		{{/if}}
		<h2>{{if $title}}{{$title}}{{else}}Catalog{{/if}}</h2>
	</div>
	<div class="section-content-wrapper">
		<table class="w-100">
			<tr>
				<th></th>
				<th>Description</th>
				<th>Price each {{if $currencysymbol}}({{$currencysymbol}}){{/if}}</th>
				<th></th>
				<th></th>
			</tr>
			{{foreach $items as $item}}
			<tr>
				<td>
					<form method="post">
						<input type="hidden" name="cart_posthook" value="add_item">
						<button class="btn btn-primary" type="submit" name="add" value="{{$item.item_sku}}">Add</button>
					</form>

				</td>
				<td>{{$item.item_desc}}</td>
				<td>{{$item.item_price}}</td>
				<td>{{if $item.order_qty}}<i class="fa fa-shopping-cart"></i> {{$item.order_qty}}{{/if}}</td>
				<td>
					{{if $item.order_qty}}
					<form method="post">
						<input type="hidden" name="cart_posthook" value="update_item">
						<input type="hidden" name="qty" value="0">
						<input type="hidden" name="id" value="{{$item.order_item_id}}">
						<button class="btn btn-outline-danger btn-outline border-0" type="submit" name="update" value="{{$item.item_sku}}" title="Remove from cart"><i class="fa fa-remove"></i></button>
					</form>
					{{/if}}
				</td>
			</tr>
			{{/foreach}}
		</table>
	</div>
</div>
