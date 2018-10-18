<div id="upgrade_info_aside" class="alert alert-info alert-dismissible fade show">
	<h3><i class="fa fa-hubzilla"></i> {{$title}}</h3>
	<hr>
	{{$content.0}}<br>
	<br>
	{{$content.1}}<br>
	<br>
	{{$content.2}} {{$content.3}} {{$content.4}}<br>
	<br>
	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
	<button id="upgrade_info_dismiss" type="button" class="btn btn-sm btn-success"><i class="fa fa-check"></i> {{$dismiss}}</button>
	<script>
		$('#upgrade_info_dismiss').click(function() {
			$.post(
				'pconfig',
				{
					'aj' : 1,
					'cat' : 'upgrade_info',
					'k' : 'version',
					'v' : '{{$std_version}}',
					'form_security_token' : '{{$form_security_token}}'
				}
			)
			.done(function() {
				$('#upgrade_info_aside').fadeOut('fast');
			});
		});
	</script>	
</div>
