<?php

namespace Bangsamu\ExportRunner\Controllers;

use App\Http\Controllers\Controller;
use Hamcrest\Type\IsNumeric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected $sheet_name = 'Notification';
    protected $sheet_slug = 'notification';
    protected $view_tabel_index = array(
        'r.id AS No',
        'r.id AS id',
        '"view" AS action',
        // 'r.type AS type',
        'r.executed_by AS generate_by',
        'DATE_FORMAT(r.created_at, "%d-%m-%Y %h:%i:%s") AS created_at',
        // 'r.report_id AS report_id',
        // 'r.job_id AS job_id',
        'r.file_name AS file_name',
        'r.params_json AS parameter',
        'r.total_data AS total_data',
    );
    protected $view_tabel = array(
        'r.id AS No',
        '"view" AS action',
        // 'r.type AS type',
        'r.executed_by AS generate_by',
        'DATE_FORMAT(r.created_at, "%d-%m-%Y %h:%i:%s") AS created_at',
        'r.report_id AS report_id',
        'r.job_id AS job_id',
        'r.file_name AS file_name',
        'r.params_json AS params_json',
        'r.total_data AS total_data',
    );

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // public function __construct(Request $request)
    // {
        // parent::__construct($request);
    // }

    public function config($id = null, $data = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data['module']['folder'] = 'module';
        $data['ajax']['url_prefix'] = $data['module']['folder'] . '.' . $sheet_slug;
        $data['page']['url_prefix'] = $sheet_slug;
        $data['page']['sheet_name'] = $sheet_name;

        $data = configDefAction($id, $data);

        $data['page']['id'] = $id;
        $data['modal']['view_path'] = $data['module']['folder'] . '.mastermodal';

        return $data;
    }

    public function index(Request $request)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;

        if ($request->ajax()) {
            return self::dataJson($request);
        }
        $page_var['view_tabel_index'] = $view_tabel_index;

        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['list'] = route('report.list');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        $page_var = compact('data');

        $page_var['formModal'] = searchConfig($data, $view_tabel_index);

        return view('master::layouts.dashboard.index', $page_var);
    }

    public function list(Request $request)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;

        // $cari = [
        //     "data" => "notif_to",
        //     "name" => "Notif To",
        //     "searchable" => "true",
        //     "orderable" => "false",
        //     "search" => [
        //         "value" => auth::user()->email,
        //         "regex" => "false",
        //     ],
        // ];

        // $requestQ = $request->all();
        // $requestQ['columns'][3] = $cari;
        // $request->merge($requestQ);

        if ($request->ajax()) {
            return self::dataJson($request, true);
        }
        $page_var['view_tabel_index'] = $view_tabel_index;

        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['list'] = route('report.list');
        $data['page']['title'] = 'Report';

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        $page_var = compact('data');

        $page_var['formModal'] = searchConfig($data, $view_tabel_index);

        return view('master::layouts.dashboard.index', $page_var);
    }

    public function dataJson(Request $request, $view_email = false)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;

        $request_length = @$request->input('length')??1;
        $limit = strpos('A|-1||', '|' . $request_length . '|') > 0 ? 10 : $request_length;
        $start = $request->input('start') ?? 0;

        $request_columns = $request->columns;
        $jml_char_nosearch = strlen(print_r($request_columns, true)); //0

        $char_nosearch = 0;
        $search = $request->input('search.value');

        $id = $request->id ?? 0;

        $offest = 0;
        $user_id = Auth::user()->id ?? 0;

        if ($request->input('order.0.column')) {
            /*remove alias*/
            $colom_filed = explode(" AS ", $view_tabel[$request->input('order.0.column')]);
            $order = $colom_filed[0] ?? 'id';
        } else {
            $order = 'r.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $totalData = DB::table('report_log as r');

        if ($view_email) {
            $totalData->where('executed_by', Auth::user()->email);
        }

        $totalData = $totalData->count();
        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('report_log as r')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->where('executed_by', Auth::user()->email);

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $builder = $data_tabel;
            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;
            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table('report_log as r')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->where('executed_by', Auth::user()->email)
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;

            $builder = $datatb_request;
            $datatb_request = $datatb_request->get();

            $data_tabel = $datatb_request;
        }

        // $mapping_json[11] = 'action';
        foreach ($view_tabel_index as $keyC => $valC) {
            /*remove alias*/
            $colom_filed = explode(" AS ", $valC);
            $c_filed = $colom_filed[1] ?? $colom_filed[0];
            $name = $mapping_json[$keyC] ?? $c_filed;
            $columnsHeader[$keyC] = $c_filed;
            $columns[$keyC] = [
                'data' => $name,
                'name' => ucwords(str_replace('_', ' ', $name)),
                // 'visible' => ($c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
                'visible' => in_array($c_filed, ['id','params_json' , 'file_name'])  ? false : true,
                'filter' => ($c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
            ];
        }

        $data = array();
        if (!empty($data_tabel)) {

            $DT_RowIndex = $start + 1;
            foreach ($data_tabel as $row) {
                $btn = '';
                $nestedData['DT_RowIndex'] = $DT_RowIndex;

                foreach ($view_tabel_index as $keyC => $valC) {

                    /*remove alias*/
                    $colom_filed = explode(" AS ", $valC);
                    $c_filed = $colom_filed[1] ?? $colom_filed[0];

                    $nestedData[$c_filed] = @$row->$c_filed;

                    $list_data = '';
                    if($c_filed=='parameter'){
                        $data_param_array = json_decode(@$row->$c_filed,true);
                        // dd($data_param_array);
                        $list_data .="<ul>";
                        foreach ($data_param_array as $key => $value) {
                            if(!in_array($key, ['job_id' , 'param__', 'param__token', 'user']) && !empty($value) ){
                                // $list_data .="<li><strong>{$key}</strong>: " . (!empty($value) ? htmlspecialchars($value) : "<em>(kosong)</em>") . "</li>";

                                 // Hilangkan prefix "param_" kalau ada
                                if (strpos($key, 'param_') === 0) {
                                    $key = substr($key, 6);
                                }

                                // Ganti _ jadi spasi dan kapital di awal setiap kata
                                $label = ucwords(str_replace('_', ' ', $key));

                                $list_data .="<li><strong>{$label}</strong>: " . (!empty($value) ? e($value) : "<em>(kosong)</em>") . "</li>";

                            }
                        }
                        $list_data .="</ul>";
                        // // dd($list_data);
                        $nestedData[$c_filed] = $list_data;
                    }

                    // $viewed = $row->viewed == 0 ? '-slash' : '';
                    // if ($c_filed == 'message' && !empty($row->$c_filed)) {
                    //     $nestedData[$c_filed] = '<a  data-type="iframe" data-fancybox="notif" href="' . route("notificatior.message", ['type' => 'html', 'id' => $row->No]) . '" class="btn  btn-sm"><i class="fa fa-fw fa-eye' . $viewed . '"></i></a> ';
                    // }
                }
                $nestedData['No'] = $DT_RowIndex;

                // Hapus base path dan ganti jadi 'storage/'
                $fullPath = base_path($row->file_name);
                $fileName = basename($row->file_name);
                $relativePath = str_replace(storage_path('app/public'), 'storage', $fullPath);
                $link_download = asset($relativePath);
                // dd($fullPath,$relativePath,$link_download);
                if (file_exists($fullPath)) {
                    $fileAgeInHours = now()->diffInHours(\Carbon\Carbon::parse($row->created_at));

                    if ($fileAgeInHours < 24) {
                        // ✅ File valid, langsung download
                        // dd(1);
                        // $btn .= '<a href="' . $link_download . '" class="btn btn-primary btn-sm">Download</a> ';
                        $btn .= '<a title="download" onclick="DownloadFile(\'' . $link_download . '\',\'' . $fileName . '\')" class=" btn btn-primary btn-sm">Download</a> ';
                    } else {
                        // dd(2);
                        // ⚠️ File expired (>1 hari), hapus
                        @unlink($fullPath);
                        DB::table('report_log')->where('id', $row->id)->delete();
                        $btn .= '<a href="#" class="btn btn-warning btn-sm">EXPIERD</a> ';

                    }
                }else{

                    $btn .= '<a href="#" class="btn btn-danger btn-sm">BROKEN</a> ';

                }

                    // $btn .= '<a href="#" class="btn btn-danger btn-sm">Delete</a> ';

                    $btn .= '<a href="' . route("report.list.destroy", ["id"=>$row->id]) . '" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a> ';

                    // <a href="http://192.168.20.187:9009/master/item_code/11" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>


                $nestedData['action'] = @$btn;

                $data[] = $nestedData;
                $DT_RowIndex++;
            }
        }

        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
            "columns" => $columns,
        );

        // if($request->debug=='true') {
                $query_debug = str_replace(array('?'), array('\'%s\''), $builder->toSql());
                // $query_debug = vsprintf($query_debug, $builder->getBindings());
                $json_data['debug'] = $query_debug;
                $json_data['getBindings'] = $builder->getBindings();
        // }

        return response()->json($json_data);
    }

    public function destroy($id)
    {
        DB::table('report_log')->where('id',$id)->delete();
        return redirect()->route('report.list', ['table' => 'report_log']);
    }
}
