<div id="no-secret" style="border: 1px solid red; padding: 3px; background: pink; display: none">
You haven't set a TOTP secret yet.
Please click the button below to generate one and register this site
with your preferred authenticator app.
</div>
<div id="has-secret" style="display: none">
Your shared secret is <b><span id="totp_secret">{{$secret}}</span></b>
<br/>Be sure to save it somewhere in case you lose or replace your mobile device.
Use your mobile device to scan the QR code below to register this site
with your preferred authenticator app.
<p><img id="totp_qrcode" src="{{$qrcode_url}}{{$salt}}" alt="QR code"/></p>
<p>
<input title="enter TOTP code from your device" type="text"
	style="width: 16em" id="totp_test"
	onkeypress="hitkey(event)"
	onfocus="totp_clear_code()"/>
<input type="button" value="Test" onclick="totp_test_code()"/>
<b><span id="totp_testres"></span></b>
</p>
</div>
<div style="float: left">
<input type="button" style="width: 16em; margin-top: 3px"
	value="Generate New Secret" onclick="expose_password()"/>
</div>
<div id="password_form" style="float: left; margin-left: 1em; display: none">
Enter your password:
<input type="password"
	style="width: 16em" id="totp_password"
	onkeypress="go_generate(event)"
	/>
<input type="button" value="Go" onclick="totp_generate_secret()"/>
</div>
<div style="clear: left"></div>
<div id="totp_note"></div>
<script type="text/javascript">
function choose_message(has_secret) {
	if (has_secret) {
		document.getElementById("no-secret").style.display = "none";
		document.getElementById("has-secret").style.display = "block";
		}
	else {
		document.getElementById("no-secret").style.display = "block";
		document.getElementById("has-secret").style.display = "none";
		}
	}
$(window).on("load", function() {
	choose_message({{$has_secret}});
	totp_clear_code();
	});
function totp_clear_code() {
	var box = document.getElementById("totp_test");
	box.value = "";
	box.focus();
	document.getElementById("totp_testres").innerHTML = "";
	}
function totp_test_code() {
	$.post('/settings/totp',
		{totp_code: document.getElementById('totp_test').value},
		function(data) {
			document.getElementById("totp_testres").innerHTML =
				(data['match'] == '1' ? 'Pass!' : 'Fail');
			});
	}
function totp_generate_secret() {
	$.post('/settings/totp',
		{
			set_secret: '1',
			password: document.getElementById("totp_password").value
			},
		function(data) {
			if (!data['auth']) {
				var box = document.getElementById("totp_password");
				box.value = "";
				box.focus();
				document.getElementById('totp_note').innerHTML =
					"Incorrect password, try again.";
				return;
				}
			var div = document.getElementById("password_form");
			div.style.display = "none";
			choose_message(true);
			document.getElementById('totp_secret').innerHTML =
				data['secret'];
			document.getElementById('totp_qrcode').src =
				"{{$qrcode_url}}" + (new Date()).getTime();
			document.getElementById('totp_note').innerHTML =
				"Record your new TOTP secret and rescan the QR code above.";
			totp_clear_code();
			});
	}
function go_generate(ev) {
	if (ev.which == 13) {
		totp_generate_secret();
		ev.preventDefault();
		ev.stopPropagation();
		}
	}
function hitkey(ev) {
	if (ev.which == 13) {
		totp_test_code();
		ev.preventDefault();
		ev.stopPropagation();
		}
	}
function expose_password() {
	var div = document.getElementById("password_form");
	div.style.display = "block";
	var box = document.getElementById("totp_password");
	box.value = "";
	box.focus();
	}
</script>
