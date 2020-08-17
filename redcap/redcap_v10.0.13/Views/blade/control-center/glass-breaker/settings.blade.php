@php
/**
 * config names:
 * - fhir_break_the_glass_enabled (disabled, FHIR, username_token)
 * - fhir_break_the_glass_username_token_base_url
 * - fhir_break_the_glass_token_usertype
 * - fhir_break_the_glass_token_username
 * - fhir_break_the_glass_token_password
 */

/**
 * helper function to check if an option
 * inside a select element is selected
 */
function isSelected($current, $option)
{
	return ($current==$option) ? 'selected' : '';
}
@endphp

{{-- title --}}
<tr>
	<td class="cc_label" style="font-weight:normal;border-top:1px solid #ccc;" colspan="2" >
		<div style="margin-bottom:10px;font-weight:bold;color:#C00000;">{{ $lang['break_glass_003'] }}</div>
		<div style="margin-bottom:10px;">{!! $lang['break_glass_004'] !!}</div>
	</td>
</tr>

{{-- is enabled --}}
<tr>
	<td class="cc_label">
        {{$lang['break_the_glass_settings_01']}}
        <div class="cc_info">
            <span>{{$lang['break_glass_002']}}</span>
        </div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:380px;" name="fhir_break_the_glass_enabled">
            <option value="" {{ isSelected($form_data['fhir_break_the_glass_enabled'], '') }}>{{ $lang['break_the_glass_disabled'] }}</option>
            <option value="access_token" {{ isSelected($form_data['fhir_break_the_glass_enabled'], 'access_token') }}>{{$lang['break_the_glass_endpoint_type_01']}}</option>
			<option value="username_token" {{ isSelected($form_data['fhir_break_the_glass_enabled'], 'username_token') }}>{{$lang['break_the_glass_endpoint_type_02']}}</option>
		</select>
        <div class="cc_info">
			<span><strong>{{$lang['break_the_glass_endpoint_type_01']}}:</strong> {!! $lang['break_the_glass_endpoint_type_01_description'] !!}</span><br/>
			<span><strong>{{$lang['break_the_glass_endpoint_type_02']}}:</strong> {!! $lang['break_the_glass_endpoint_type_02_description'] !!}</span><br/>
		</div>
	</td>
</tr>

{{-- EHR user type --}} 
<tr>
	<td class="cc_label">
		{{ $lang['break_glass_007'] }}
		<div class="cc_info">{!! $lang['break_glass_ehr'] !!}</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="fhir_break_the_glass_ehr_usertype">
			@foreach($ehr_user_types as $ehr_user_type)
			<option value="{{$ehr_user_type}}" {{ isSelected($form_data['fhir_break_the_glass_ehr_usertype'], $ehr_user_type) }}>{{$ehr_user_type}}</option>
			@endforeach
		</select>
		<div class="cc_info">
			<span>{!! $lang['break_glass_usertype_ehr'] !!}</span><br/>
		</div>
	</td>
</tr>

{{-- username token base URL --}}
<tr data-depends-on="fhir_break_the_glass_enabled">
	<td class="cc_label">
		{{ $lang['break_glass_017'] }}
		<div class="cc_info">
			{{ $lang['break_glass_018'] }}
		</div>
	</td>
	<td class="cc_data">
		<input class="x-form-text x-form-field" style="width:350px;" type="text" name="fhir_break_the_glass_username_token_base_url" value="{{ htmlspecialchars($form_data['fhir_break_the_glass_username_token_base_url'], ENT_QUOTES) }}" /><br/>
		<!-- <div class="cc_info">{{ $lang['break_glass_018'] }}</div> -->
	</td>
</tr>


{{-- username_token user type --}} 
<tr data-depends-on="fhir_break_the_glass_enabled">
	<td class="cc_label">
		{{ $lang['break_glass_008'] }}
		<div class="cc_info">{!! $lang['break_glass_usertype'] !!}</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="fhir_break_the_glass_token_usertype">
			<option value="EMP" {{ isSelected($form_data['fhir_break_the_glass_token_usertype'], 'EMP') }}>EMP</option>
			<option value="local" {{ isSelected($form_data['fhir_break_the_glass_token_usertype'], 'local') }}>Local</option>
			<option value="windows" {{ isSelected($form_data['fhir_break_the_glass_token_usertype'], 'windows') }}>Windows</option>
		</select>
		<div class="cc_info">
			<span>{!! $lang['break_glass_usertype_emp'] !!}</span><br/>
			<span>{!! $lang['break_glass_usertype_local'] !!}</span><br/>
			<span>{!! $lang['break_glass_usertype_windows'] !!}</span><br/>
		</div>
	</td>
</tr>

{{-- token credentials --}}
<tr data-depends-on="fhir_break_the_glass_enabled">
	<td class="cc_label">
		{{ $lang['break_glass_005'] }}
		<div class="cc_info">
		{{ $lang['break_glass_006'] }}
		</div>
	</td>
	<td class="cc_data">
		<table style="width:100%;">
			<tbody>
				<tr>
					<td style="color:#800000;padding-bottom:5px;font-weight:bold;" class="nowrap">{{ $lang['global_11'] }}:</td>
					<td style="padding-bottom:5px;">
						<input class="x-form-text x-form-field" style="width:320px;" type="text" name="fhir_break_the_glass_token_username" value="{{ htmlspecialchars($form_data['fhir_break_the_glass_token_username'], ENT_QUOTES) }}">
					</td>
				</tr>
				<tr>
					<td style="color:#800000;font-weight:bold;" class="nowrap">{{ $lang['global_32'] }}:</td>
					<td>
						<input class="x-form-text x-form-field" style="width:220px;" autocomplete="new-password" type="password" name="fhir_break_the_glass_token_password" value="{{ htmlspecialchars($form_data['fhir_break_the_glass_token_password'], ENT_QUOTES) }}" aria-autocomplete="list">
						<a href="javascript:;" class="cclink" style="text-decoration:underline;font-size:7pt;margin-left:5px;" onclick="$(this).remove();showPasswordField('fhir_break_the_glass_token_password');">Show password</a>
					</td>
				</tr>
			</tbody>
		</table>
	</td>
</tr>

{{-- department ID --}}
{{--
<tr>
	<td class="cc_label">
		{{ $lang['break_glass_019'] }}
		<div class="cc_info">{{ $lang['break_glass_020'] }}</div>
	</td>
	<td class="cc_data">
		<input class="x-form-text x-form-field" style="width:350px;" type="text" name="fhir_break_the_glass_department_id" value="{{ htmlspecialchars($form_data['fhir_break_the_glass_department_id'], ENT_QUOTES) }}" /><br/>
	</td>
</tr>
--}}

{{-- department ID type --}}
{{--
@php
$fhir_break_the_glass_department_id_types = array('Internal','External','ExternalKey','Name','CID','IIT')
@endphp
<tr>
	<td class="cc_label">
		{{ $lang['break_glass_021'] }}
		<div class="cc_info">{{ $lang['break_glass_022'] }}</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="fhir_break_the_glass_department_id_type">
		@foreach($fhir_break_the_glass_department_id_types as $department_id_type)
			<option value="{{ $department_id_type }}" {{ isSelected($form_data['fhir_break_the_glass_department_id_type'], $department_id_type) }}>{{ $department_id_type }}</option>
		@endforeach
		</select>
	</td>
</tr>
--}}

<script>
	(function(window, document) {
		/**
		 * enable/disable options based on the selected Break the glass mode
		 */
		function initBreakTheGlassSettings(select_element) {
			// check for settings as the page is loaded
			updateSettings(select_element)
			// listen for changes and update settings
			select_element.addEventListener('change', function() {
				updateSettings(select_element)
			})
		}
		
		/**
		 * update settings based on the break the glasss mode
		 * some options will only be visible in username_token mode
		 */
		function updateSettings(select_element) {
			// get the selected mode
			var mode = select_element.value;
			// get a list of options depending on the mode
			var depending_options = document.querySelectorAll('[data-depends-on]');
			var username_token_mode = 'username_token';
			var access_token_mode = 'access_token';
			var hidden = mode!=username_token_mode;
			depending_options.forEach(function(element) {
				if(hidden) element.classList.add('hidden')
				else element.classList.remove('hidden')
			})
		}
		
		document.addEventListener('DOMContentLoaded', function(event) {
			// name of the option to observe
			var option_name = 'fhir_break_the_glass_enabled';
			// get a reference to the select element
			var break_the_glass_mode_select = document.querySelector('[name="'+option_name+'"]');
			initBreakTheGlassSettings(break_the_glass_mode_select);
		})
	}(window, document))
</script>