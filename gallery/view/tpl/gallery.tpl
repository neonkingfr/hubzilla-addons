{{if !$aj}}
<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$title}}</h2>
	</div>
	<div class="section-content-wrapper-np">
		<div id="photo-albums" style="display: none">
			{{foreach $albums as $album}}
				<div class="init-gallery cursor-pointer" data-aid="{{$album.folder}}" data-album="{{$album.album}}">
					<img src="photo/{{$album.resource_id}}-3" width="{{$album.width}}" height="{{$album.height}}" alt="{{$album.album}}" />
				</div>
			{{/foreach}}
		</div>
	</div>
</div>
{{/if}}

<script>
	$(document).ready(function() {
		{{if ! $aj}}
		justifyPhotos('photo-albums');
		{{/if}}

		var gallery = {};
		var aid = '';
		var album = '';
		var share_str = '';

		// items array
		{{if ! $aj}}
		var items = [];
		{{/if}}

		{{if $json}}
		var items = {{$json}};
		{{/if}}

		// define options
		var options = {
			index: 0, // start at first slide
			preload: [1, 3],
			shareButtons: [
				{{if ! $aj}}
				{ id: 'conv_link', label: 'View conversation', url: 'photos/{{$nick}}/image/\{\{raw_image_url\}\}' },
				{{/if}}
				{ id: 'download', label: 'Download fullsize image', url: 'photo/\{\{raw_image_url\}\}', download: true }
				],
			getImageURLForShare: function( shareButtonData ) {
				return gallery.currItem.resource_id;
			}
		};

		var pswpElement = document.querySelectorAll('.pswp')[0];

		if(items.length) {
			// Initializes and opens PhotoSwipe
			gallery = new PhotoSwipe(pswpElement, PhotoSwipeUI_Default, items, options);
			gallery.init();
		}
		{{if ! $aj}}
		$(document).on('click', '.init-gallery', function() {
			album_id = $(this).data('aid');
			album = $(this).data('album');
			share_str = '';
			$.post(
				'gallery/{{$nick}}',
				{
					'album_id' : album_id,
					'album' : album,
					'unsafe' : {{$unsafe}}
				},
				function(items) {
					var i;
					for (i = 0; i < (items.length > 1 ? 1 : items.length); i++) { //keep preview image count at 1 until we have a solution to nicely present multiple photos 
						share_str += '[zrl=' + encodeURIComponent(baseurl + '/gallery/{{$nick}}/' + album) + ']Album: ' + album + '[/zrl]' + "\r\n" + '[zrl=' + encodeURIComponent(baseurl + '/gallery/{{$nick}}/' + album + '?pid=' + (i+1)) + '][zmg]' + encodeURIComponent(items[i].src) + '[/zmg][/zrl]';
					}
					options.shareButtons.splice(2, 1, { id: 'share_link', label: 'Share this album', url: 'rpost?f=&title=' + encodeURIComponent('Album: ' + album) + '&body=' + share_str });
					// Initializes and opens PhotoSwipe
					gallery = new PhotoSwipe(pswpElement, PhotoSwipeUI_Default, items, options);
					gallery.init();
				},
				'json'
			);
		});
		{{/if}}
	});
</script>
