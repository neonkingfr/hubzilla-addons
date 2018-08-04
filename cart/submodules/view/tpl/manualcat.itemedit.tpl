<div id="cart-manualcat-edititem-wrapper">
  <h3>Edit Item: {{$sku}}</h3>
  <div id="cart-manualcat-itemdetails-wrapper"><div class="panel panel-default">
    <div class="panel-heading"><div class="panel-title">
      <h4><a data-toggle="collapse" data-parent="#cart-manualcat-itemedit-wrapper" href="#itemdetails">Item Details</a></h4>
    </div></div>
    <div id="itemdetails" class="panel-collapse collapse in"><div id="cart-manualcat-edititem-form-wrapper">
      <h1>Item Details</h1>
      <form id="cart-manualcat-edititem-form" method="post" action="{{$formelements.uri}}">
      <input type=hidden name="form_security_token" value="{{$security_token}}">
      <input type=hidden name="cart_posthook" value="manualcat_itemedit">
      <input type=hidden name="SKU" value="{{$sku}}">
      {{$formelements.itemdetails}}
      {{$formelements.item}}
      <button id="itemdetails-submit" class="btn btn-primary" type="submit" name="submit" >{{$formelements.submit}}</button>
      </form>
    </div></div>
  </div></div>
</div>
