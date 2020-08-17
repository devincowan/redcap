<script src="{{$app_path_js}}Chart.min.js" defer></script>

<div class="fhir-stats">
    <h4 class="title"><i class="far fa-chart-bar "></i> {{$lang['dashboard_126']}}</h4>
    @include('control-center.fhir-stats.form')
    
    @if($show)
        @if(!empty($results))
        

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="overall-tab" data-toggle="tab" href="#overall" role="tab" aria-controls="overall" aria-selected="true">Overall</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="cdm-tab" data-toggle="tab" href="#cdm" role="tab" aria-controls="cdm" aria-selected="false">Clinica Data Mart</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="cdp-tab" data-toggle="tab" href="#cdp" role="tab" aria-controls="cdp" aria-selected="false">Clinical Data Pull</a>
            </li>

            <ul class="nav navbar-nav ml-auto">
                <li><a class="btn btn-sm btn-secondary btn-defaultrc" href="{{$export_link}}"><i class="fas fa-file-archive"></i> {{$lang['fhir_stats_04']}}</a></li>
            </ul>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="overall" role="tabpanel" aria-labelledby="overall-tab">
                @php($overall_data = $results['data']['overall'])
                @include('control-center.fhir-stats.partials.tab-pane', array(
                    'name' => 'overall',
                    'data' => $overall_data,
                ))
            </div>

            <div class="tab-pane fade" id="cdm" role="tabpanel" aria-labelledby="cdm-tab">
                @if($cdm_users_count = $results['data']['cdm_users_count'])
                <div class="redcap-alert alert-secondary" role="alert">
                    <strong>Total Clinical Data Mart users:</strong> {{$cdm_users_count}}
                </div>
                @endif
                
                @php($cdm_data = $results['data']['CDM'])
                @include('control-center.fhir-stats.partials.tab-pane', array(
                    'name' => 'cdm',
                    'data' => $cdm_data,
                ))

            </div>

            <div class="tab-pane fade" id="cdp" role="tabpanel" aria-labelledby="cdp-tab">
                @php($cdp_data = $results['data']['CDP'])
                @include('control-center.fhir-stats.partials.tab-pane', array(
                    'name' => 'cdp',
                    'data' => $cdp_data,
                ))
            </div>

        </div>
        @else
            <p>Sorry, no results.</p>
        @endif
    @endif
    
</div>

<style>
    .redcap-alert {
        border-radius: .25rem;
        border: 1px solid red transparent;
        padding: .75rem 1.25rem;
        margin-bottom: 1rem;
    }
</style>