2FA Active
<input type="checkbox" value="1"
	onclick="totp_set_active(this)" {{$checked}}/>
<div>
Your shared secret is <b><span id="totp_secret">{{$secret}}</span></b>
</div>
<div>
Be sure to save it somewhere in case you lose or replace your mobile device.
<br/>QR code provided for your convenience:
<p><img id="totp_qrcode" src="{{$qr_img_url}}" alt="QR code"/></p>
<div>
<input title="enter TOTP code from your device" type="text"
	style="width: 16em" id="totp_test"
	onkeypress="hitkey(event)"
	onfocus="totp_clear_code()"/>
<input type="button" value="Test" onclick="totp_test_code()"/>
<b><span id="totp_testres"></span></b>
</div>
<div>
<input type="button" style="width: 16em; margin-top: 3px"
	value="Generate New Secret" onclick="totp_generate_secret()"/>
</div>
<div id="totp_remind" style="display:none">Record your new TOTP secret and rescan the QR code above.
</div>
<script type="text/javascript">
function totp_set_active(cb) {
	$.post("totp", {active: (cb.checked ? "1" : "0")});
	}
function totp_clear_code() {
	document.getElementById("totp_test").value = "";
	document.getElementById("totp_testres").innerHTML = "";
	}
function totp_test_code() {
	$.post('totp',
		{totp_code: document.getElementById('totp_test').value},
		function(data) {
			document.getElementById("totp_testres").innerHTML =
				(data['match'] == '1' ? 'Pass!' : 'Fail');
			});
	}
function totp_generate_secret() {
	$.post('totp',
		{secret: '1'},
		function(data) {
			document.getElementById('totp_secret').innerHTML =
				data['secret'];
			document.getElementById('totp_qrcode').src =
				data['pngurl'];
			document.getElementById('totp_remind').style.display =
				'block'
			});
	}
function hitkey(ev) {
	if (ev.which == 13) {
		totp_test_code();
		ev.preventDefault();
		ev.stopPropagation();
		}
	}
</script>
