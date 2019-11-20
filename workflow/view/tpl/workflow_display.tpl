<div class="content-fluid">
<div class="workflow toolbar">
        <div class="workflow toolbar row">
                {{$toolbar}}
        </div>
</div>
		<script>
			var wfitemid = {{$items.0.item_id}};
			var itemPostURL = '{{$posturl}}';
			var uuid = '{{$uuid}}';
			var mid = '{{$mid}}';
		</script>
<div class="row">
	<div class="col-xs-12 col-sm-6 col-md-9">
		<div id="wfitemdata">
		{{include file="./workflow_display_wfitemdata.tpl"}}
		</div>
		<div class="row" style="min-height:500px;">
			<div id="workflowDisplayMain" class="col-12 workflow wfmainiframe" style="height:max-height;border:solid 1px;padding:0px;">{{$maindata}}</div>
		</div>
	</div>
	<div class="col-xs-12 col-sm-6 col-md-3">
		<div class="workflow wfsidebar">
			{{if ($items.0.related)}}
				<h4>Related Links</h4>
				{{foreach $items.0.related as $related}}
					<div class="workflow wfrelatedlink">
						<b>{{if $related.title}}{{$related.title}}{{else}}{{$related.relatedlink|wordwrap:18:" ":true}}{{/if}}</b><br>
						<!--
						<a href="#" class='workflow-showmodal-iframe' onclick="return false;" data-posturl='{{$posturl}}' data-action='{{$related.action}}' data-miscdata='{{$related.jsondata}}' data-toggle="tooltip" title="pop-up"><i class='fa fa-window-restore'></i></a>
						-->
						<a href="#" class='workflow-showmain-iframe' onclick="return false;" data-posturl='{{$posturl}}' data-action='{{$related.action}}' data-miscdata='{{$related.jsondata}}' data-toggle="tooltip" title="pop-up"><i class='fa fa-window-restore'></i></a>
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
