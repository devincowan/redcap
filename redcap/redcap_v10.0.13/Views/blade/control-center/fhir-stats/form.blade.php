<form id="fhir-stats-form" class="mb-3" method="GET">
    @if($_GET[route])
    <input type="hidden" name="route" value="{{$_GET[route]}}" />
    @endif

    <div class="form-row">
        <div class="form-group date-wrapper">
            <label for="period">{{$lang['fhir_stats_02']}}</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i class="far fa-calendar"></i></div>
                </div>
                <input type="hidden" name="date_start" />
                <input type="text" class="form-control date start" data-alt="date_start" value="{{$date_start}}" placeholder="{{$lang['fhir_stats_02']}}" readonly>
            </div>
        </div>
        <div class="form-group date-wrapper">
            <label for="period">{{$lang['fhir_stats_03']}}</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i class="far fa-calendar"></i></div>
                </div>
                <input type="hidden" name="date_end" />
                <input type="text" class="form-control date end" data-alt="date_end" value="{{$date_end}}" placeholder="{{$lang['fhir_stats_03']}}" readonly>
            </div>
        </div>

        <div class="ml-2">
            <button type="submit" class="btn btn-success">{{$lang['fhir_stats_01']}}</button>
            {{-- <button type="reset" class="ml-2 btn btn-danger">Reset</button> --}}
        </div>
    </div>
    <input type="hidden" name="show" value="1" />
</form>

<script>
(function(window,document,$){

    var form_selector = '#fhir-stats-form'
    var date_start_selector = '.date.start'
    var date_end_selector = '.date.end'

    function initDatePickers() {
        const config = {
            changeMonth: true,
            changeYear: true,
            dateFormat: 'mm-dd-yy',
            altFormat: 'yy-mm-dd',
            constrainInput: false,
            onSelect: updateRangeConstraints,
            maxDate: new Date(),
        }
        // set common configuration for all date fields
        $('.date').datepicker(config)
        // set alt field for dates. alfield stores teh date in a database compatible format (Y-m-d)
        $('.date').each(function(index) {
            var alt_field_name = this.getAttribute('data-alt')
            $(this).datepicker('option', 'altField', '[name="'+alt_field_name+'"]')
            $(this).datepicker('setDate', $(this).val()) // set initial value if provided
        })
        // set the date to null if delete or cancel are pressed
        $('.date').keyup(function(e) {
            var delete_codes = [8,46] // 8=backspace, 46=delete
            if(delete_codes.indexOf(e.keyCode)>=0) {
                $(this).datepicker('setDate', '')
                $(this).blur()
                // reset min and max date
                $('.date').datepicker("option", "minDate", '')
                $('.date').datepicker("option", "maxDate", '')
            }
        })
        updateRangeConstraints() //set range if dates are set on load
    }

    /**
     * update min and max date for date range
     */
    function updateRangeConstraints() {
        var $date_start = $(date_start_selector)
        var $date_end = $(date_end_selector)
        var date_start = $date_start.datepicker( "getDate" )
        $date_end.datepicker("option", "minDate", date_start)
        var date_end = $date_end.datepicker( "getDate" ) || new Date() //today is the maximum date anyway
        $date_start.datepicker("option", "maxDate", date_end)
    }

    /* function onSubmit(e) {
        e.preventDefault()
        this.submit()
    } */

    document.addEventListener("DOMContentLoaded",function(){
        initDatePickers()
        // $(form_selector).submit(onSubmit)
    })
}(window,document,jQuery))
</script>

<style>

#fhir-stats-form .form-row {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: space-between;
}
#fhir-stats-form .form-group {
    margin-left: 5px;
}
#fhir-stats-form label {
    display: block;
}
ul#myTab li a.nav-link {
    font-size: 10px;
}
#fhir-stats-form .form-group {
    flex: 1;
}
#fhir-stats-form .date {
    background-color: #fff;
}
#fhir-stats-form label {
    text-transform: capitalize;
    display: none;
}
</style>