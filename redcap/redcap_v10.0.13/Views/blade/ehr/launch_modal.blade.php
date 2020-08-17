    <div class="modal" id="fhir_launch_modal" tabindex="-1" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fas fa-notes-medical"></i> {{$lang['ehr_launch_modal_01']}}</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
				<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p>{{$lang['ehr_launch_modal_02']}}</p>
                <p >EHR system: <strong>{{$ehr_system_name}}</strong></p>
				<div class="info-panel yellow">
					<span>{{$lang['ehr_launch_modal_03']}}</span>
				</div>
			</div>
			<div class="modal-footer">
				<a href="{{$app_path_webroot}}ehr.php?standalone_launch=1" class="btn btn-primary text-white">{{$lang['bottom_91']}} {{$ehr_system_name}}</a>
				<button type="button" id="launch-stop-asking" class="btn btn-secondary btn-sm" data-dismiss="modal" data-stop="yes">{{$lang['bottom_89']}}</button>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{$lang['bottom_90']}}</button>
			</div>
			</div>
		</div>
	</div>
	<style>
	.info-panel {
		padding: 5px;
		border: solid 1px rgba(0,0,0,.2);
		border-radius: .25rem;
	}
	.info-panel.yellow {
		background-color: #fff3cd;
	}
	</style>
    <script type="text/javascript">
		function initStopAskingButton(stop_asking_cookie_name) {
			var stop_asking_button = document.getElementById("launch-stop-asking")
			stop_asking_button.addEventListener('click', function(){
				setCookie(stop_asking_cookie_name,true,7);
			})
		}

		function showFhirLaunchModal() {
			var stop_asking = getCookie('fhir-launch-stop-asking');
			if (!stop_asking) {
				var $modal = $('#fhir_launch_modal');
				if ($modal.length) $modal.modal('show');
			}
		}

		$(function(){
			initStopAskingButton('fhir-launch-stop-asking');
			if (page == 'DataMartController:index') {
				showFhirLaunchModal();
			}
		});
	</script>