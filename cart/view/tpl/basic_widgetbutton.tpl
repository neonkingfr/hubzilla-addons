<div class="cart-button-wrapper dm42cart button" style="border-width:0px;">
    <div class="cart-button dm42cart button">
	{{if $item.order_qty}}
        	{{if $item.maxcount != 1}} 
	        <form method="post" style="border-width:0px;" action="{{$posturl}}?returnurl={{$returnurl}}">
			<input type="hidden" name="cart_posthook" value="update_item">
			<input class="form-control form-control-sm" type="text" name="qty-{{$item.id}}" value="{{$item.order_qty}}" style="width: 4em;float:left;">
		        <button class="btn btn-primary" type="submit" name="Submit" value="{{$item.item_sku}}">Update Quantity</button>
			<button class="btn btn-outline-danger btn-outline border-0" type="submit" name="delsku" value="{{$item.item_sku}}" title="Remove from cart"><i class="fa fa-remove"></i></button>
		</form>
                {{else}}
	        <form method="post" style="border-width:0px;" action="{{$posturl}}?returnurl={{$returnurl}}">
			<input type="hidden" name="cart_posthook" value="update_item">
			<input type="hidden" name="delsku" value="{{$item.item_sku}}">
			<b>Item Already in your cart!</b>
			<button class="btn btn-outline-danger btn-outline border-0" type="submit" name="remove" title="Remove from cart"><i class="fa fa-remove"></i></button>
		</form>
		{{/if}}
        {{else}}
	<form method="post" style="border-width:0px;" action="{{$posturl}}{{if $returnurl}}?returnurl={{$returnurl}}{{/if}}">
		<input type="hidden" name="cart_posthook" value="add_item">
		<input class="form-control form-control-sm" type="text" name="qty" value="1" style="width: 4em;float:left;">
		<button class="btn btn-primary" type="submit" name="add" value="{{$item.item_sku}}">Add to Cart</button>
	</form>
	{{/if}}
    </div>
<div style="clear:both;"></div>
</div>
