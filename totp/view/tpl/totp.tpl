<div style="width: 30em; margin: auto; margin-top: 3em; padding: 1em; border: 1px solid grey">
<h3 style="text-align: center">{{$header}}</h3>

<div>{{$desc}}</div>

<form action="totp" method="post">
	<div style="margin: auto; margin-top: 1em; width: 18em">
	<input type="text" class="form-control" style="float: left; width: 8em" name="totp-code" id="totp-code"/>
	<input type="button" style="margin-left: 1em; float: left" value={{$submit}} onclick="totp_verify()"/>
	<div style="clear: left"></div>
	<div id="feedback" style="margin-top: 4px; text-align: center"></div>
	</div>
</form>
</div>
<script type="text/javascript">
function totp_clear() {
	var box = document.getElementById("totp-code");
	box.value = "";
	box.focus();
	}
function totp_verify() {
	var code = document.getElementById("totp-code").value;
	$.post("totp", {totp_code: code},
		function(resp) {
			var report = document.getElementById("feedback");
			var box = document.getElementById("totp-code");
			if (resp['match'] == "1") {
				report.innerHTML = "<b>Success!</b>";
				}
			else {
				report.innerHTML = "Invalid code, please try again.";
				totp_clear();
				}
			});
	}
totp_clear();
</script>
