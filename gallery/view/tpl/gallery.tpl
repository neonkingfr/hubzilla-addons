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

<script type="module">
	import PhotoSwipeLightbox from '/addon/gallery/lib/photoswipe5/dist/photoswipe-lightbox.esm.min.js';

	$(document).ready(function() {
		{{if ! $aj}}
		justifyPhotos('photo-albums');
		{{/if}}

		let gallery = {};
		let album_id = '';
		let album = '{{$album}}';

		// items array
		{{if ! $aj}}
		let items = [];
		{{/if}}

		{{if $json}}
		let items = {{$json}};
		{{/if}}

		if(items.length) {
			pswp_init(items, album);
		}

		{{if ! $aj}}
		$(document).on('click', '.init-gallery', function() {
			album_id = $(this).data('aid');
			album = $(this).data('album');

			$.post(
				'gallery/' + {{$channel_nick}},
				{
					'album_id' : album_id,
					'album' : album,
					'unsafe' : {{$unsafe}}
				},
				function(items) {
					pswp_init(items, album);
				},
				'json'
			);
		});
		{{/if}}

	});

	function pswp_init(items, album) {
		let share_str = '';

		let options = {
			preload: [1, 3],
			bgOpacity: 1,
			bgClickAction: 'toggle-controls',
			dataSource: items,
			pswpModule: () => import('/addon/gallery/lib/photoswipe5/dist/photoswipe.esm.js'),
		};

		const lightbox = new PhotoSwipeLightbox(options);

		lightbox.on('uiRegister', function() {
			lightbox.pswp.ui.registerElement({
				name: 'download-button',
				title: 'Download this photo',
				order: 8,
				isButton: true,
				tagName: 'a',
				html: '<i class="fa fa-download text-white" style="padding: 1.7rem; font-size: 1rem"></i>',
				onInit: (el, pswp) => {
					el.setAttribute('download', '');
					el.setAttribute('target', '_blank');
					el.setAttribute('rel', 'noopener');

					pswp.on('change', () => {
						el.href = pswp.currSlide.data.osrc;
					});
				}
			});
		});

		if (album) {
			let i;

			for(i = 0; i < (items.length > 8 ? 8 : items.length); i++) {
				share_str += '[zrl=' + encodeURIComponent(baseurl + '/gallery/' + {{$channel_nick}} + '/' + album + '?f=%23%26gid=1%26pid=' + (i+1)) + '][zmg]' + encodeURIComponent(items[i].src) + '[/zmg][/zrl]';
			}
			share_str += '[zrl=' + {{$observer_url}} + ']' + {{$observer_name}} + '[/zrl] shared [zrl=' + {{$channel_url}} + ']' + {{$channel_name}} + '[/zrl]\'s [zrl=' + encodeURIComponent(baseurl + '/gallery/' + {{$channel_nick}} + '/' + album) + ']album[/zrl] ' + encodeURIComponent(album) + ' (' + items.length + ' images)';

			if (share_str) {
				lightbox.on('uiRegister', function() {
					lightbox.pswp.ui.registerElement({
						name: 'share-button',
						title: 'Share this album',
						order: 9,
						isButton: true,
						tagName: 'a',
						html: '<i class="fa fa-share text-white" style="padding: 1.7rem; font-size: 1rem"></i>',
						onInit: (el, pswp) => {
							el.setAttribute('target', '_blank');
							el.setAttribute('rel', 'noopener');
							el.href = 'rpost?f=&title=' + encodeURIComponent('Album: ' + album) + '&body=' + share_str;
						}
					});
				});
			}
		}

		lightbox.init();
		lightbox.loadAndOpen(0);
	}

</script>
