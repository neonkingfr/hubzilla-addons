<div class="content-fluid">
<div class="row">
	<div class="col-xs-12 col-sm-6 col-md-9">
		<div class="row">
		<div class="col-12 workflow wfheading">
			{{$items.0.title}}
 			<a href="#" onclick='return false;' class="workflow-showmodal-iframe" data-posturl='{{$posturl}}' data-action='{{$addlinkaction}}' data-miscdata='{{$edittaskjsondata}}' data-toggle="tooltip" title="edit"><i class="fa fa-pencil"></i></a>
		</div></div>
		<div class="row">
			{{foreach $itemmeta as $meta}}<div class="workflow wfmeta-item {{if $meta.cols}}{{$meta.cols}}{{/if}}">{{$meta.html}}
			</div>
			{{/foreach}}
		</div>
		<div class="row">
		<div class="col-12 workflow wfcontent">
			{{$body.html}}
		</div></div>
	</div>
	<div class="col-xs-12 col-sm-6 col-md-3">
		<div class="workflow wfsidebar">
			{{if ($items.0.related)}}
				<h4>Related Links</h4>
				{{foreach $items.0.related as $related}}
					<div class="workflow wfrelatedlink">
						<b>{{if $related.title}}{{$related.title}}{{else}}{{$related.relatedlink|wordwrap:18:" ":true}}{{/if}}</b><br>
						<a href="#" class='workflow-showmodal-iframe' onclick="return false;" data-posturl='{{$posturl}}' data-action='{{$related.action}}' data-miscdata='{{$related.jsondata}}' data-toggle="tooltip" title="pop-up"><i class='fa fa-window-restore'></i></a>
 						<a href="{{$related.relurl}}" target="{{$related.uniq}}" data-toggle="tooltip" title="new window"><i class="fa fa-external-link"></i></a>
 						<a href="#" onclick='return false;' class="workflow-showmodal-iframe" data-posturl='{{$posturl}}' data-action='{{$addlinkaction}}' data-miscdata='{{$related.jsoneditdata}}' data-toggle="tooltip" title="edit"><i class="fa fa-pencil"></i></a>
						<br>
						{{if $related.notes}}{{$related.notes}}{{else}}
						<span style="font-size:.75em">{{$related.relatedlink|truncate:50:"...":true:true|wordwrap:25:" ":true}}</span>
						{{/if}}
					<hr>
					</div>
				{{/foreach}}
			{{/if}}
		<div class="workflow-ui"><a href="#" id='workflow-addlink-plus' onclick="return false;" data-posturl='{{$posturl}}' data-action='{{$addlinkaction}}' data-miscdata='{{$addlinkmiscdata}}' data-toggle="tooltip" title="Add new link"><i class="fa fa-plus"></i></a></div>
		</div>
	</div>
</div>
</div>
