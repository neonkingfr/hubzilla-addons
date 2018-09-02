<!-- Modal -->
<div class="modal fade" id="channelrepModal" tabindex="-1" role="dialog" aria-labelledby="channelrepModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="channelrepModalLabel">Channel Reputation</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="channelrepSecurityToken" name="channelrepSecurityToken" value="{{$security_token}}">
        <input type="hidden" id="channelrepId" name="channelrepId" value="{{$channelrepId}}">
        <h5 class="modal-title" id="channelrePointsLabel">Points</h5>
        <input type="text" id="channelrepPoints name="channelrepPoints" value="{{$pointssuggestion}}">
        <button type="button" class="channelrepAdd" aria-hidden="true" onClick="channelrepPlus();">+</button>
        <button type="button" class="channelrepSubtract" aria-hidden="true" onClick="channelrepMinus();">-</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
