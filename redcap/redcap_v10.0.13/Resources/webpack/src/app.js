// jQuery and jQuery UI
require('webpack-jquery-ui');
require('webpack-jquery-ui/css');
require("jquery-ui-touch-punch");
require("expose-loader?$!jquery");

// Bootstrap
import 'bootstrap';
require('popper.js');
import 'bootstrap/dist/css/bootstrap.min.css';
//import './scss/app.scss';

// Select2
import 'select2/dist/css/select2.min.css';
require('select2');

// SweetAlert2
import Swal from 'sweetalert2';

// DataTables
import 'datatables.net-dt/css/jquery.dataTables.min.css';
var dt = require('datatables.net');
require('datatables.net-fixedcolumns');
require('datatables.net-fixedheader');