<div style="width: 30em; margin: auto; margin-top: 3em; padding: 1em; border: 1px solid grey">
<h3 style="text-align: center">{{$header}}</h3>

<div>{{$desc}}</div>

<form action="totp" method="post">
	<div style="margin: auto; margin-top: 1em; width: 18em">
	<input type="text" class="form-control" style="float: left; width: 8em" name="totp-code" id="totp-code"/>
	<input type="submit" name="submit" style="margin-left: 1em; float: left" value={{$submit}} />
	<div style="clear: left"></div>
	</div>
</form>
</div>
