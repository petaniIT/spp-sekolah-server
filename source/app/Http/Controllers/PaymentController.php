<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Routing\Redirector;
use App\FinancingCategory;
use App\Payment;
use App\Student;
use App\PaymentPeriodeDetail;
use App\PaymentPeriode; 
use App\PaymentDetail;
use App\PaymentView;
use App\Major;
use App\Pencatatan;
use App\Income;
use App\Cicilan;
use DB;

class PaymentController extends Controller
{
    public function __construct()
    {
        set_time_limit(300);
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    { 
        
        $datas = FinancingCategory::selectRaw('financing_categories.*, getJumlahTunggakanKategori(financing_categories.id) as tunggakan, 
        getCountNunggakPeriodeUseKategori(financing_categories.id) as tunggakan_periode')
                ->orderBy('updated_at','desc')->get();
        $no = 1;
        return view('pembayaran.index', compact('datas', 'no'));
    }

    public function indexKategori($id)
    {
        $students = Student::all();
        $no=1;
        $jml = Major::count();
        $majors = Major::all();
        return view('pembayaran.siswa', compact('students','no','jml','majors'));

        $siswa = $id;
        $datas = FinancingCategory::all();
        $no = 1;
        return view('pembayaran.index', compact('datas', 'no','siswa'));
    }

    public function indexKategori2($siswa)
    {
        $category = Student::all();
        $siswa = Student::where('id', $siswa)->get();
        $siswa = $siswa[0];
        DB::statement(DB::raw('set @row:=0'));
        $periodes = PaymentPeriode::select(DB::raw('@row:=@row+1 as rowNumber'),'payment_periodes.*')
                                    ->where('financing_category_id','2')
                                    ->get();
        
        return view('pembayaran.kategori',compact('periodes','category','siswa'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
         //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'nama' => 'required',
            'besaran' => 'required',
        ]);

        try {
            $req = $request->all();
            FinancingCategory::create([
                'id' => null,
                'nama' => $req['nama'],
                'besaran' => $req['besaran'],
            ]);
            $id = DB::getPdo()->lastInsertId();
            //untuk history perubahan harga
            FinancingCategoryReset::create([
                'id' => null,
                'financing_category_id' => $id,
                'besaran' => $req['besaran'],
            ]);

        return redirect()
            ->route('financing.index')
            ->with('success', 'Data jurursan berhasil disimpan!');

        }catch(Exception $e){
        return redirect()
            ->route('financing.create')
            ->with('error', 'Data jurursan gagal disimpan!');
        }
        
    }

    /**
     * Display the specified resource.
    *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //numbering
        $no = 1;
        //data siswa
        //cek jenis kategori
        $cek = FinancingCategory::findOrFail($id);
        if($cek->jenis=="Bayar per Bulan")
        {
           
            // $datas=DB::table('students')
            //             ->selectRaw('students.*,getNominalTerbayarBulanan(payments.id) AS terbayar, getCountBulananTidakTerbayar(payments.id) AS bulan_tidak_bayar, getCountNunggak(payments.id) as cekNunggak, getCountWaiting(payments.id) AS cekWaiting, majors.nama AS jurusan, getAkumulasiPerBulan(payments.id) AS akumulasi, financing_categories.`nama` AS financing_nama, financing_categories.id AS financing_id, payments.`id` AS payment_id, payments.`jenis_pembayaran`')
            //             ->leftJoin('majors','majors.id','=','students.major_id')
            //             ->leftJoin('payments','payments.student_id','=','students.id')
            //             ->leftJoin('financing_categories','financing_categories.id','=','payments.financing_category_id')
            //             ->leftJoin('payment_details','payment_details.payment_id','=','payments.id')
            // $datas = Student::selectRaw('students.*, payments.*, financing_categories.id as financing_category_id, financing_categories.nama as kategori, financing_categories.jenis    ')
            //                 ->join('payments','payments.student_id','=','students.id')
            //                 ->join('financing_categories','financing_categories.id','=','payments.financing_category_id')
            //                 ->groupBy('students.id')
            //                 ->where('financing_categories.id',$cek->id)->get();

            // $payments = Payment::where('financing_category_id', $id)->orderBy('updated_at','desc')->get();
            
            // $datas = $payments;

            $financing = $cek;
            
            $periode = PaymentPeriode::where('financing_category_id',$id)->count(); 
            
            $payments = Payment::where('financing_category_id', $id)->get();
            
            return view('pembayaran.show', compact('financing','periode','no'));
        }else{

            $financing = $cek;

            $periode = PaymentPeriode::where('financing_category_id',$id)->count(); 

            $payment_details = PaymentDetail::all();
            $cicilans = Cicilan::all();
            
            $fil = '';
            $fil2 = '';
            $kls = '';

            $majors = PaymentPeriode::select('major_id')->where('financing_category_id',$id)->groupBy('major_id')->get();
            $angkatan = PaymentPeriode::select('angkatan_id')->where('financing_category_id',$id)->groupBy('angkatan_id')->get();

            return view('pembayaran.show_sekali_bayar_2', compact('financing','periode','no','payment_details','cicilans','majors','angkatan','fil','fil2','kls'));
        }
    }
    public function ajaxIndex($id)
    {
        $cek = $_GET;
        try {
            $kelas = $cek['kelas'] == "all" ? "" : $cek['kelas'];
        } catch (\Throwable $th) {
            $kelas = "";
        }
        try {
            $jurusan = $cek['jurusan'] == "all" ? "" : $cek['jurusan'];
        } catch (\Throwable $th) {
            $jurusan = "";
        }
        try {
            $angkatan = $cek['angkatan'] == "all" ? "" : $cek['angkatan'];
        } catch (\Throwable $th) {
            $angkatan = "";
        }
        if ($kelas != "" && $jurusan != "" && $angkatan != "") {
            $datas = Payment::
                selectRaw('
                    students.nama, 
                    students.kelas, 
                    majors.inisial as jurusan,
                    financing_periodes.nominal,
                    financing_categories.jenis, 
                    payments.persentase,
                    payments.jenis_pembayaran,
                    getPaymentDetailsId(payments.id) as payment_detail_tunai,
                    getNominalCicilan(getPaymentDetailsId(payments.id)) as cicilan,
                    students.id as student_id,
                    financing_categories.id as financing_category_id,
                    payments.id,
                    payments.jenis_potongan,
                    payments.nominal_potongan
                ')
                ->join('students','payments.student_id','=','students.id')
                ->join('majors','majors.id','=','students.major_id')
                ->join('financing_categories','financing_categories.id','=','payments.financing_category_id')
                ->join('payment_details','payment_details.payment_id','=','payments.id')
                ->leftJoin('cicilans','cicilans.payment_detail_id','=','payment_details.id')
                ->join('financing_periodes','financing_periodes.id','=','payment_details.payment_periode_id')
                ->where('financing_categories.id',$id)
                ->where('students.kelas', $kelas)
                ->where('students.major_id', $jurusan)
                ->where('students.angkatan_id', $angkatan)
                ->groupBy('students.id')
                ->orderBy('payments.updated_at','desc')
                ->get();
        } 
        elseif ($kelas == "" && $jurusan != "" && $angkatan != "") {
            $datas = Payment::
                selectRaw('
                    students.nama, 
                    students.kelas, 
                    majors.inisial as jurusan,
                    financing_periodes.nominal,
                    financing_categories.jenis, 
                    payments.persentase,
                    payments.jenis_pembayaran,
                    getPaymentDetailsId(payments.id) as payment_detail_tunai,
                    getNominalCicilan(getPaymentDetailsId(payments.id)) as cicilan,
                    students.id as student_id,
                    financing_categories.id as financing_category_id,
                    payments.id,
                    payments.jenis_potongan,
                    payments.nominal_potongan
                ')
                ->join('students','payments.student_id','=','students.id')
                ->join('majors','majors.id','=','students.major_id')
                ->join('financing_categories','financing_categories.id','=','payments.financing_category_id')
                ->join('payment_details','payment_details.payment_id','=','payments.id')
                ->leftJoin('cicilans','cicilans.payment_detail_id','=','payment_details.id')
                ->join('financing_periodes','financing_periodes.id','=','payment_details.payment_periode_id')
                ->where('financing_categories.id',$id)
                ->where('students.major_id', $jurusan)
                ->where('students.angkatan_id', $angkatan)
                ->groupBy('students.id')
                ->orderBy('payments.updated_at','desc')
                ->get();
        } 
        elseif ($kelas == "" && $jurusan != "" && $angkatan == "") {
            $datas = Payment::
                selectRaw('
                    students.nama, 
                    students.kelas, 
                    majors.inisial as jurusan,
                    financing_periodes.nominal,
                    financing_categories.jenis, 
                    payments.persentase,
                    payments.jenis_pembayaran,
                    getPaymentDetailsId(payments.id) as payment_detail_tunai,
                    getNominalCicilan(getPaymentDetailsId(payments.id)) as cicilan,
                    students.id as student_id,
                    financing_categories.id as financing_category_id,
                    payments.id,
                    payments.jenis_potongan,
                    payments.nominal_potongan
                ')
                ->join('students','payments.student_id','=','students.id')
                ->join('majors','majors.id','=','students.major_id')
                ->join('financing_categories','financing_categories.id','=','payments.financing_category_id')
                ->join('payment_details','payment_details.payment_id','=','payments.id')
                ->leftJoin('cicilans','cicilans.payment_detail_id','=','payment_details.id')
                ->join('financing_periodes','financing_periodes.id','=','payment_details.payment_periode_id')
                ->where('financing_categories.id',$id)
                ->where('students.major_id', $jurusan)
                ->groupBy('students.id')
                ->orderBy('payments.updated_at','desc')
                ->get();
        } 
        elseif ($kelas != "" && $jurusan == "" && $angkatan != "") {
            $datas = Payment::
                selectRaw('
                    students.nama, 
                    students.kelas, 
                    majors.inisial as jurusan,
                    financing_periodes.nominal,
                    financing_categories.jenis, 
                    payments.persentase,
                    payments.jenis_pembayaran,
                    getPaymentDetailsId(payments.id) as payment_detail_tunai,
                    getNominalCicilan(getPaymentDetailsId(payments.id)) as cicilan,
                    students.id as student_id,
                    financing_categories.id as financing_category_id,
                    payments.id,
                    payments.jenis_potongan,
                    payments.nominal_potongan
                ')
                ->join('students','payments.student_id','=','students.id')
                ->join('majors','majors.id','=','students.major_id')
                ->join('financing_categories','financing_categories.id','=','payments.financing_category_id')
                ->join('payment_details','payment_details.payment_id','=','payments.id')
                ->leftJoin('cicilans','cicilans.payment_detail_id','=','payment_details.id')
                ->join('financing_periodes','financing_periodes.id','=','payment_details.payment_periode_id')
                ->where('financing_categories.id',$id)
                ->where('students.kelas', $kelas)
                ->where('students.angkatan_id', $angkatan)
                ->groupBy('students.id')
                ->orderBy('payments.updated_at','desc')
                ->get();
        } 
        elseif ($kelas != "" && $jurusan == "" && $angkatan == "") {
            $datas = Payment::
                selectRaw('
                    students.nama, 
                    students.kelas, 
                    majors.inisial as jurusan,
                    financing_periodes.nominal,
                    financing_categories.jenis, 
                    payments.persentase,
                    payments.jenis_pembayaran,
                    getPaymentDetailsId(payments.id) as payment_detail_tunai,
                    getNominalCicilan(getPaymentDetailsId(payments.id)) as cicilan,
                    students.id as student_id,
                    financing_categories.id as financing_category_id,
                    payments.id,
                    payments.jenis_potongan,
                    payments.nominal_potongan
                ')
                ->join('students','payments.student_id','=','students.id')
                ->join('majors','majors.id','=','students.major_id')
                ->join('financing_categories','financing_categories.id','=','payments.financing_category_id')
                ->join('payment_details','payment_details.payment_id','=','payments.id')
                ->leftJoin('cicilans','cicilans.payment_detail_id','=','payment_details.id')
                ->join('financing_periodes','financing_periodes.id','=','payment_details.payment_periode_id')
                ->where('financing_categories.id',$id)
                ->where('students.kelas', $kelas)
                ->groupBy('students.id')
                ->orderBy('payments.updated_at','desc')
                ->get();

        } 
        elseif ($kelas != "" && $jurusan != "" && $angkatan == "") {
            $datas = Payment::
                selectRaw('
                    students.nama, 
                    students.kelas, 
                    majors.inisial as jurusan,
                    financing_periodes.nominal,
                    financing_categories.jenis, 
                    payments.persentase,
                    payments.jenis_pembayaran,
                    getPaymentDetailsId(payments.id) as payment_detail_tunai,
                    getNominalCicilan(getPaymentDetailsId(payments.id)) as cicilan,
                    students.id as student_id,
                    financing_categories.id as financing_category_id,
                    payments.id,
                    payments.jenis_potongan,
                    payments.nominal_potongan
                ')
                ->join('students','payments.student_id','=','students.id')
                ->join('majors','majors.id','=','students.major_id')
                ->join('financing_categories','financing_categories.id','=','payments.financing_category_id')
                ->join('payment_details','payment_details.payment_id','=','payments.id')
                ->leftJoin('cicilans','cicilans.payment_detail_id','=','payment_details.id')
                ->join('financing_periodes','financing_periodes.id','=','payment_details.payment_periode_id')
                ->where('financing_categories.id',$id)
                ->where('students.kelas', $kelas)
                ->where('students.major_id', $jurusan)
                ->groupBy('students.id')
                ->orderBy('payments.updated_at','desc')
                ->get();
        }
        else {
            $datas = Payment::
                selectRaw('
                    students.nama, 
                    students.kelas, 
                    majors.inisial as jurusan,
                    financing_periodes.nominal,
                    financing_categories.jenis, 
                    payments.persentase,
                    payments.jenis_pembayaran,
                    getPaymentDetailsId(payments.id) as payment_detail_tunai,
                    getNominalCicilan(getPaymentDetailsId(payments.id)) as cicilan,
                    students.id as student_id,
                    financing_categories.id as financing_category_id,
                    payments.id,
                    payments.jenis_potongan,
                    payments.nominal_potongan
                ')
                ->join('students','payments.student_id','=','students.id')
                ->join('majors','majors.id','=','students.major_id')
                ->join('financing_categories','financing_categories.id','=','payments.financing_category_id')
                ->join('payment_details','payment_details.payment_id','=','payments.id')
                ->leftJoin('cicilans','cicilans.payment_detail_id','=','payment_details.id')
                ->join('financing_periodes','financing_periodes.id','=','payment_details.payment_periode_id')
                ->where('financing_categories.id',$id)
                ->groupBy('students.id')
                ->orderBy('payments.updated_at','desc')
                ->get();
        }
        $result = array();
        foreach ($datas as $i => $data) {
            //Main
            $persentase = $data->persentase;
            $_besaran = intval($data->nominal);
            $_persentase = intval($data->persentase);
            $_nominal_potongan = intval($data->nominal_potongan);

            //Besaran
            $besaran = number_format($_besaran,0,',','.');

            //Persentase
            //Potongan
            if ($data->jenis_potongan != "persentase") {
                $_persentase = ($_nominal_potongan/$_besaran)*100;
            }
            else
            {
                $_nominal_potongan = $_besaran * ($persentase / 100);    
            }
            $persentase = number_format($_persentase,0,',','.');
            $potongan = number_format($_nominal_potongan,0,',','.');
            
            //Terbayar
            $_terbayar = intval($data->cicilan);
            $terbayar = number_format($_terbayar,0,',','.');
            
            //Sisa
            $_sisa = $_besaran - $_nominal_potongan - $_terbayar;
            $sisa = number_format($_sisa,0,',','.');
            
            //Metode
            if ($data->jenis_pembayaran == "Waiting")
            {
                $metode =
                '
                <div style="text-align:center">
                    <span class="badge"
                        style="background-color:yellow;color:black">
                        Waiting
                    </span>
                </div>
                ';
            }
            else
            {
                $metode =
                '
                <div style="text-align:center">
                    '.$data->jenis_pembayaran.'
                </div>
                ';
            }

            //Status
            if( $data->jenis_pembayaran=="Waiting")
            {
                $status =
                '
                <div style="text-align:center">
                    <span class="badge"
                    style="background-color:yellow;color:black">
                    Waiting
                    </span>
                </div>
                ';
            }
            else if($data->jenis_pembayaran=="Nunggak")
            {
                $status =
                '
                <div style="text-align:center">
                    <span class="badge" style="background-color:red">Nunggak</span>
                </div>
                ';
            }
            else if($data->jenis_pembayaran=="Cicilan" && $_sisa != 0)
            {
                $status =
                '
                <div style="text-align:center">
                    <span class="badge" style="background-color:yellow;color:black">
                        Belum Lunas
                    </span>
                </div>
                ';
            }
            else
            {
                $status =
                '
                <div style="text-align:center">
                    <span class="badge" style="background-color:green">Lunas</span>
                </div>
                ';
            }

            //Action
            $obj = json_encode($data);
            $link_rincian = url('payment/details')."/{$data->financing_category_id}/{$data->student_id}/{$data->id}";
            $link_print = url('export/sesekali/detail')."/{$data->student_id}/{$data->payment_detail_tunai}/tunai";
            $link_delete = url('payment/detail/delete')."/{$data->payment_detail_tunai}";
            $action =
            '
            <div style="text-align:center">
                <a href="'.$link_print.'"
                class=" btn btn-success" target="_blank" title="Cetak kwitansi">
                    <i class="fa fa-print"></i>
                </a>
                <a href="'.$link_delete.'" 
                class="btn btn-danger" style="color:white;margin-top:0" title="Delete">
                    <i class="fa fa-close"></i>
                </a> 
            </div>
            ';
            if($data->jenis_pembayaran=="Waiting" || $data->jenis_pembayaran=="Nunggak")
            {
                $action =
                '
                <div style="text-align:center">
                    <button class="btn btn-warning"
                        onclick=\'addConfirm('.$obj.',"'.$besaran.'")\'
                        title="Pilih Metode Pembayaran" style="color:black;  ">
                        <i class="fa fa-info-circle"> Metode</i>
                    </button>
                </div>
                ';
            }
            else if($data->jenis_pembayaran=="Cicilan")
            {
                $action =
                '
                <div style="text-align:center">
                    <a href="'.$link_rincian.'"
                    class="btn btn-primary" title="Rincian Cicilan Siswa"
                    style="color:white;">
                        <i class="fa fa-eye"> Rincian</i>
                    </a>
                </div>
                ';
            }
            
            $temp = (object) array(
                "no" => $i + 1,
                "nama" => $data->nama,
                "kelas" => "{$data->kelas} - {$data->jurusan}",
                "besaran" => $besaran,
                "persentase" => $persentase,
                "potongan" => $potongan,
                "terbayar" => $terbayar,
                "sisa" => $sisa,
                "metode" => $metode,
                "status" => $status,
                "action" => $action
            );

            
            // echo "<pre>";
            // var_dump ($data); 
            // echo "{$data->nominal} | {$data->nominal_potongan} | {$data->nominal} | {$data->nominal} | {$action}";
            // dd($temp);
            // echo "</pre>";
            // echo"<hr>";
            $result[] = $temp;
        }
        // echo '<pre>';
        // var_dump(json_encode($result));
        // dd(json_encode($result[0]));
        // die;
        return json_encode($result);
    }

    public function ajaxIndexPerbulan($id)
    {
        return Payment::
                selectRaw('
                    payments.id,
                    payments.student_id,
                    payments.financing_category_id,
                    students.nama,
                    students.kelas,
                    majors.inisial as jurusan,
                    financing_periodes.nominal,
                    (SELECT count(*) 
                    from payment_details
                    where payment_details.payment_id = payments.id
                    and status <> "Lunas"
                    ) as sisa_bulan,
                    (SELECT count(*) 
                    from payment_details
                    where payment_details.payment_id = payments.id
                    and status = "Nunggak"
                    ) as spp_nunggak,
                    (SELECT count(*) 
                    from payment_details
                    where payment_details.payment_id = payments.id
                    and status = "Waiting"
                    ) as spp_waiting
                ')
                ->join('students','payments.student_id','=','students.id')
                ->join('majors','majors.id','=','students.major_id')
                ->join('payment_details','payment_details.payment_id','=','payments.id')
                ->join('financing_periodes','financing_periodes.id','=','payment_details.payment_periode_id')
                ->where('payments.financing_category_id',$id)
                ->orderBy('payments.updated_at','desc')
                ->groupBy('students.id')
                ->get();
    }

    //Show filter Data 

    public function showFilter(Request $request)
    {
        
        $cek = FinancingCategory::findOrFail($request->id_kategori);

        if($request->kelas=="all"){
            $request->kelas = '';
        }
        if($request->jurusan=="all"){
            $request->jurusan = '';
        }
        if($request->angkatan=="all"){
            $request->angkatan = '';
        }

        $financing = $cek;

        $periode = PaymentPeriode::where('financing_category_id',$request->id_kategori)->count(); 
        
        $payments = Payment::join('students','payments.student_id','=','students.id')->where('financing_category_id', $request->id_kategori)->orderBy('payments.updated_at', 'desc')->get();
        
        if($request->kelas=='' && $request->jurusan!='' && $request->angkatan==''){
            $payments = Payment::join('students','payments.student_id','=','students.id')->where([
                ['financing_category_id', '=',$request->id_kategori],
                ['students.major_id', '=',$request->jurusan],
            ])->orderBy('payments.updated_at', 'desc')->get();
        }elseif ($request->jurusan=='' && $request->kelas!='' && $request->angkatan=='') {
            $payments = Payment::join('students','payments.student_id','=','students.id')->where([
                ['financing_category_id', '=',$request->id_kategori],
                ['students.kelas', '=',$request->kelas],
            ])->orderBy('payments.updated_at', 'desc')->get();
        }elseif ($request->jurusan=='' && $request->kelas=='' && $request->angkatan!='') {
            $payments = Payment::join('students','payments.student_id','=','students.id')->where([
                ['financing_category_id', '=',$request->id_kategori],
                ['students.angkatan_id', '=',$request->angkatan],
            ])->orderBy('payments.updated_at', 'desc')->get();
        }elseif ($request->jurusan!='' && $request->kelas!='' && $request->angkatan=='') {
            $payments = Payment::join('students','payments.student_id','=','students.id')->where([
                ['financing_category_id', '=',$request->id_kategori],
                ['students.kelas', '=',$request->kelas],
                ['students.major_id', '=',$request->jurusan],
            ])->orderBy('payments.updated_at', 'desc')->get();
        }elseif ($request->jurusan=='' && $request->kelas!='' && $request->angkatan!='') {
            $payments = Payment::join('students','payments.student_id','=','students.id')->where([
                ['financing_category_id', '=',$request->id_kategori],
                ['students.kelas', '=',$request->kelas],
                ['students.angkatan_id', '=',$request->angkatan],
            ])->orderBy('payments.updated_at', 'desc')->get();
        }elseif ($request->jurusan!='' && $request->kelas=='' && $request->angkatan!='') {
            $payments = Payment::join('students','payments.student_id','=','students.id')->where([
                ['financing_category_id', '=',$request->id_kategori],
                ['students.major_id', '=',$request->jurusan],
                ['students.angkatan_id', '=',$request->angkatan],
            ])->orderBy('payments.updated_at', 'desc')->get();
        }elseif ($request->jurusan!='' && $request->kelas!='' && $request->angkatan!='') {
            $payments = Payment::join('students','payments.student_id','=','students.id')->where([
                ['financing_category_id', '=',$request->id_kategori],
                ['students.kelas', '=',$request->kelas],
                ['students.major_id', '=',$request->jurusan],
                ['students.angkatan_id', '=',$request->angkatan],
            ])->orderBy('payments.updated_at', 'desc')->get();
        }else{
            $payments = Payment::join('students','payments.student_id','=','students.id')
            ->where('financing_category_id', $request->id_kategori)
            ->orderBy('payments.updated_at', 'desc')->get();
        }

        $datas = $payments;

        $payment_details = PaymentDetail::all();
        $cicilans = Cicilan::all();
        
        $fil = $request->jurusan;
        $fil2 = $request->angkatan;
        $kls = $request->kelas;
        $no = 1;

        $majors = PaymentPeriode::select('major_id')->where('financing_category_id',
        $request->id_kategori)->groupBy('major_id')->get();
        $angkatan = PaymentPeriode::select('angkatan_id')->where('financing_category_id',
        $request->id_kategori)->groupBy('angkatan_id')->get();

        return view('pembayaran.show_sekali_bayar', compact('datas','financing','periode',
        'no','payment_details','cicilans','majors','angkatan','fil','fil2','kls'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request,[
            'nama' => 'required',
            'besaran' => 'required',
        ]);

        try {
          $req = $request->all();
          $data = FinancingCategory::findOrFail($id);
          $data->nama = $req['nama'];
          $data->besaran = $req['besaran'];
          $data->save();
          FinancingCategoryReset::create([
            'id' => null,
            'financing_category_id' => $id,
            'besaran' => $req['besaran'],
        ]);

          return redirect()
              ->route('financing.index')
              ->with('success', 'Data telah diubah!');

        } catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
          return redirect()
              ->route('financing.index')
              ->with('error', 'Data gagal diubah!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $cek = FinancingCategory::findOrFail($id);
            FinancingCategory::destroy($id);
            FinancingCategoryReset::where('financing_category_id', $id)->delete();
            $payments = Payment::where('financing_category_id', $id)->get();
            if($cek->jenis=="Bayar per Bulan"){
                PaymentPeriode::where('financing_category_id', $id)->delete();
                for ($i=0; $i < $payments->count(); $i++) { 
                    PaymentPeriodeDetail::where('payment_id', $payments[$i]->id)->delete();
                }
            }else{
                for ($i=0; $i < $payments->count(); $i++) { 
                    PaymentDetail::where('payment_id', $payments[$i]->id)->delete();
                }
            }
            Payment::where('financing_category_id', $id)->delete();
            return redirect()
            ->route('financing.index')
            ->with('success', 'Berhasil dihapus!');

        } catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return redirect()
                ->route('financing.index')
                ->with('error', 'Gagal dihapus!');
      }
        
    } 

    public function history($id)
    {
        DB::statement(DB::raw('set @row:=0'));
        return FinancingCategoryReset::select(DB::raw('@row:=@row+1 as rowNumber, format(besaran,0) as besaran'),'created_at')
                                    ->where('financing_category_id',$id)
                                    ->get();;
    }
    
    /**
     * @description Mencari nilai besaran pada kategori pembiayaan tertentu
     * 
     * @param id FinancingCategory kategori pembiayaan sebagai kata kunci
     * @return {int} mengembalikan nilai besaran
     */
    public function getBesaranBiayaKategoriPembiayaan($id)
    {
        $data = FinancingCategory::select('besaran')
                                ->where('id',$id)
                                ->get();
        return $data[0]->besaran;
    }

    /**
     * @description Mencari nilai besaran biaya telah terbayar
     * 
     * @param id Student
     * @param id Category dari FinancingCategory
     * @return {int} nominal biaya telah dibayar
     */
    public function getBesaranBiayaTerbayar($id_student, $id_category)
    {
        $data = PaymentDetail::selectRaw('sum(nominal) as nominal')
                            ->join('payments','payment_details.payment_id','=','payments.id')
                            ->join('students','payments.student_id','=','students.id')
                            ->where([
                                ['students.id','=',$id_student],
                                ['payments.financing_category_id','=',$id_category]
                            ])
                            ->get();
        return intval($data[0]->nominal);
    }

    public function storeMetodePembayaran(Request $request)
    {
        $req = $request->all();
        $payment = Payment::findOrFail($req['payment_id']);
        // echo '<pre>';
        // var_dump($payment);
        // echo "<hr>";
        if ($request->jenis_potongan == "nominal")
        {
            $payment->jenis_potongan = "nominal";
            $payment->persentase = 0;
            $payment->nominal_potongan = $request->persentase;
            // echo "nominal, update jenis potongan dengan nominal, persentase jadi nol dan isi field nominal_potongan dengan isi persentase ";
        }
        else
        {
            $payment->jenis_potongan = "persentase";
            $payment->persentase = $request->persentase;
            $payment->nominal_potongan = 0;
            // echo "persentase, update jenis potongan dengan persentase, nominal_potongan jadi nol dan isi field persentase dengan isi persentase ";
        }
        $payment->save();
        // var_dump($req);
        // echo "<hr>";
        // var_dump($payment);
        // die;
        $req['date'] = date('Y-m-d', time());
        $req['user_id'] = Auth::user()->id;
        $obj = Student::where('id',$req['student_id'])->first();
        $percent = (int) $req['persentase'];
        $nominal = intval($req['dump'])-((intval($req['dump'])*$percent)/100);
        if($req['metode_pembayaran']=='Tunai')
        {
            if ($request->jenis_potongan == "nominal")
            {
                $nominal = intval($request->nominal_bayar);
            }
            $cek = json_decode($req['data']);
            $date = $this->convertToCorrectDateValue($request->tanggal_bayar);
            $penerima = Auth::user()->name;
            if($obj['kelas']=='ALUMNI')
            {
                $desc = "Pembayaran Tunai ".$req['financing_category']." dari ".$obj['nama']." ".$obj['kelas']." ( ".$obj->major->nama." )"." diterima oleh ".$penerima;
            }else
            {
                $desc = "Pembayaran Tunai ".$req['financing_category']." dari ".$obj['nama']." kelas ".$obj['kelas']." ( ".$obj->major->nama." )"." diterima oleh ".$penerima;
            }
            
            $payment = Payment::findOrFail($req['payment_id']);
            $detail_new = PaymentDetail::findOrFail($payment->detail->first()->id);

            $payment->jenis_pembayaran = "Tunai";
            $payment->persentase = $percent;
            $payment->save();
            
            $detail_new->tgl_dibayar = $date;
            $detail_new->nominal = $nominal;
            $detail_new->bulan = $date;
            $detail_new->user_id = $req['user_id'];
            $detail_new->status = "Lunas";
            $detail_new->save();

            $cicilan_new = Cicilan::create([
                'id' => null,
                'payment_detail_id' => $detail_new->id,
                'tgl_dibayar' => $date,
                'nominal' => $nominal,
                'user_id' => Auth::user()->id,
            ]);
                
            $title = "Pembayaran Tunai {$req['financing_category']} {$obj['nama']}";
            Income::create([
                'id' => null,
                'payment_detail_id' => $detail_new->id,
                'cicilan_id' => $cicilan_new->id,
                'title' => $title,
                'description' => $desc,
                'sumber' => "Siswa",
                'nominal' => $nominal,
            ]);
            
            $id = DB::getPdo()->lastInsertId();

            Pencatatan::create([
                'id' => null,
                'expense_id' => 0,
                'income_id' => $id,
                'debit' => $nominal,
                'description' => $desc,
                'kredit' => 0,
            ]);
                
            if($request->set_simpanan == "1"){
                $simpan = intval($request->dump) - intval($request->nominal);
                $obj->simpanan += $simpan;
                $obj->save();
            }

            return redirect()
            ->route('payment.show', $req['financing_category_id'])
            ->with('success', 'Lunas!');
        }
        elseif($req['metode_pembayaran']=='Nunggak'){
            $cek = Payment::where('id',$req['payment_id'])->first();
            $cek->jenis_pembayaran="Nunggak";
            $cek->save();
            $cek = PaymentDetail::where('payment_id',$req['payment_id'])->first();
            $cek->status="Nunggak";
            $cek->save();
            return redirect()
            ->route('payment.show', $req['financing_category_id'])
            ->with('success', 'Status pembayaran disimpan!');
        }else
        {
            $cek = Payment::where('id',$req['payment_id'])->first();
            if($cek->jenis_pembayaran != "Tunai"){
                $cek->jenis_pembayaran="Cicilan";
                $cek->persentase = $percent;
                $cek->save();
                return redirect()
                    ->route('payment.details.cicilan', [$req['financing_category_id'], $req['student_id'], $cek->id])
                    ->with('success', 'Metode Pembayaran disimpan!');
            }
            return redirect()
                ->route('payment.show', $req['financing_category_id'])
                ->with('error', 'Metode Pembayaran telah di isi');
        }
    }

    /**
     * @description me
     * 
     * @param id id dari kategori pembayaran
     * @param id_siswa id dari siswa bersangkutan
     * @param id_payment id dari payment siswa bersangkutan
     */
    public function details($id, $id_siswa, $id_payment)
    {
        //numbering
        $no = 1;
        //data siswa
        $datas=Student::selectRaw('students.*, payments.jenis_pembayaran,`getBesaranBiayaTerbayar`(students.id, '.$id.') AS terbayar')
                ->leftJoin('payments','payments.student_id','=','students.id')
                ->leftJoin('financing_categories', 'financing_categories.id', '=', 'payments.financing_category_id')
                ->leftJoin('payment_details','payment_details.payment_id','=','payments.id')
                ->groupBy('students.id')
                ->where('students.id', $id_siswa)
                ->get();
        //data master show data untuk header
        $financing = FinancingCategory::findOrFail($id)
                    // ->selectRaw('*, (getBesaranBiayaKategoriPembiayaan(financing_categories.id)) as besaran, (financing_categories.`besaran` - ((select persentase from payments p2 where p2.id = '.$id_payment.')*financing_categories.besaran)/100) AS akumulasi')
                    ->where('id',$id)
                    ->get();
        $financing = $financing[0];
        //data Pembiayaan
        $payments = Payment::where('id',$id_payment)->first();
        $payment_details = PaymentDetail::where('payment_id',$id_payment)->get();
        
        //Untuk penghitung banyak periode pembayaran
        $periode = PaymentPeriode::where('financing_category_id',$id)->count(); 
        
        $cicilans = Cicilan::where('payment_detail_id', $payment_details[0]->id)->get();

        $footer['total'] = $payments->periode[0]->nominal;
        $footer['terbayar'] = $cicilans->sum('nominal');
        if ($payments->jenis_potongan == "persentase")
        {
            $footer['potongan'] = floor(intval($payments->periode[0]->nominal)*intval($payments->persentase)/100);
        }
        else
        {
            $footer['potongan'] = floor($payments->persentase);
        }
        $footer['sisa'] = $footer['total'] - $footer['potongan'] - $footer['terbayar'];
        if ($periode==0 && $financing->nama=="Bayar per Bulan") {
            return redirect()
                ->route('payment.index')
                ->with('error', 'Periode pembiayaan kosong. Untuk Pembiayaan dengan jenis per Bulan, periode harus dicantumkan!');
        }

        $date = $this->getTanggalHariIni();
        
        return view('pembayaran.cicilan2', compact('datas','financing','payments', 'payment_details','periode','no','date','cicilans','footer'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cicilanStore(Request $request)
    {
        $request = $request->all();
        if (!isset($request['calendar'])) {
            return redirect()
                ->route('payment.details.cicilan', [$request['financing_category_id'], $request['student_id'], $request['payment_id']])
                ->with('error', 'Tanggal tidak boleh kosong!');
        }

        $uang_tunai = intval($request['uang']);
        $uang_simpanan = $request['gunakan_simpanan']=="1" ? intval($request['nominal']) - $uang_tunai : 0;
        
        $sudah_dibayar = Cicilan::where('payment_detail_id', $request['payment_detail_id'])->sum('nominal');
        $tamp = Cicilan::where('payment_detail_id', $request['payment_detail_id'])->count();

        $hitungan = $tamp+1;
        $total = floor($request['sisa']);
        $bayar = intval($request['nominal']);
        $digunakan = $bayar - intval($request['uang']);
        $selisih = $total - $bayar;
        $simpan = $bayar - $total;
        $nominal = $bayar;
        $status="Nunggak";
        //
        /**
         * Total 400.000
         * sudah dibayar 0
         * selisih = 400.000 - (0 + 5000000) = -100.000
         * sisa= 500.000 - 100.000 = 400.000
         * 
         */
        if($selisih<0){
            $nominal = $total;
            $status = 'Lunas';
        }elseif($selisih==0){
            $status='Lunas';
        }
        $penerima=Auth::user()->name;
        $siswa=Student::where('id',$request['student_id'])->first();
        $category=FinancingCategory::where('id',$request['financing_category_id'])->first();
        $desc = "Penerimaan pembayaran cicilan ke {$hitungan} untuk {$category['nama']} dari {$siswa['nama']} kelas {$siswa['kelas']} {$siswa->major->nama} diterima oleh {$penerima}";
        $date = $this->convertToCorrectDateValue($request['calendar']);
        Cicilan::create([
            'id' => null,
            'payment_detail_id' => $request['payment_detail_id'],
            'tgl_dibayar' => $date,
            'nominal' => $nominal,
            'simpanan' => $uang_simpanan,
            'tunai' => $uang_tunai,
            'user_id' => Auth::user()->id,
        ]);

        $last_id = DB::getPdo()->lastInsertId();
        
        $title = "CICILAN {$category['nama']} {$siswa['nama']}";
        Income::create([
            'id' => null,
            'cicilan_id' => $last_id,
            'title' => $title,
            'description' => $desc,
            'sumber' => "Siswa",
            'nominal' => $nominal,
        ]);
            
        $last_id = DB::getPdo()->lastInsertId();
        
        if($request['gunakan_simpanan']=="1"){
            $siswa['simpanan'] = $siswa['simpanan'] + $simpan - $digunakan;
            $siswa->save();
        }elseif($request['set_simpanan']=="1"){
            $siswa['simpanan'] = $siswa['simpanan'] + $simpan;
            $siswa->save();
        }


        if($status=="Lunas"){
            $details = PaymentDetail::where('payment_id', $request['payment_id'])->get();
            foreach ($details as $d) {
                $d->status=$status;
                $d->save();
            }
        }
        Pencatatan::create([
            'id' => null,
            'expense_id' => 0,
            'income_id' => $last_id,
            'debit' => $nominal,
            'description' => $desc,
            'kredit' => 0,
        ]);
        return redirect()
                ->route('payment.details.cicilan', [$request['financing_category_id'], $request['student_id'], $request['payment_id']])
                ->with('success', 'Pembayaran disimpan!');
    }


    

    // ================================== Method Pembayaran Per Bulan ===================================== //
    /**
     * menampilkan data siswa dalam kategori pembayaran bulanan
     * 
     * @param int id kategori pembayaran
     * 
     */
    public function showBulanan($id)
    {
        
        
        //numbering
        $no = 1;
        //data siswa
        $datas=Student::selectRaw('students.*, payments.jenis_pembayaran,`getBesaranBiayaTerbayar`(students.id, '.$id.') AS terbayar')
                ->leftJoin('payments','payments.student_id','=','students.id')
                ->leftJoin('financing_categories', 'financing_categories.id', '=', 'payments.financing_category_id')
                ->leftJoin('payment_details','payment_details.payment_id','=','payments.id')
                ->groupBy('students.id')
                ->get();

        //data master show data untuk header
        $financing = FinancingCategory::findOrFail($id)
                    ->selectRaw('*, getBesaranBiayaKategoriPembiayaan(financing_categories.id) as besaran')
                    ->where('id',$id)
                    ->get();
        $financing = $financing[0];
        //Untuk penghitung banyak periode pembayaran
        $periode = PaymentPeriode::where('financing_category_id',$id)->count(); 
        // $students = Student::all();
        if ($periode==0 && $financing->nama=="Bayar per Bulan") {
            return redirect()
                ->route('payment.index')
                ->with('error', 'Periode pembiayaan kosong. Untuk Pembiayaan dengan jenis per Bulan, periode harus dicantumkan!');
        }
        // echo "<pre>";
        // var_dump($datas);
        return view('pembayaran.perbulan', compact('datas','financing','periode','no'));
    }

    public function showBulananDetail($payment, $id_student, $category)
    {
        $financing = FinancingCategory::where('id',$category)->get();
        $financing = $financing[0];
        
        //Untuk penghitung banyak periode pembayaran
        $periode = PaymentPeriode::where('financing_category_id',$category)->count(); 
    
        if ($periode==0 && $financing->nama=="Bayar per Bulan") {
            return redirect()
                ->route('payment.index')
                ->with('error', 'Periode pembiayaan kosong. Untuk Pembiayaan dengan jenis per Bulan, periode harus dicantumkan!');
        }
  
        //numbering
        $no = 1;
        //data Pembiayaan
        $payments = Payment::where('id',$payment)->first();
        $payment_details = PaymentDetail::where('payment_id',$payment)->orderBy('bulan')->get();

        $date = $this->getTanggalHariIni();
        
        return view('pembayaran.detail_bulanan2', compact('financing','no','date','payment_details'));
    }

    public function bulananStore(Request $request)
    {
        $this->validate($request,[
            'status' => 'required',
            'payment_id' => 'required',
        ]);
        $req = $request->all();
        try {
            $siswa = Student::findOrFail($req['siswa']);
            $bulan = $this->convertToBulan($req['bulan']);
            $desc = "Penerimaan pembayaran {$req['pembayaran']} untuk bulan {$bulan} {$req['tahun']} dari {$siswa->nama} kelas {$siswa->kelas} {$siswa->major->nama} diterima oleh {$req['penerima']}";
            //Update data Pembayaran
            $pembayaran = PaymentPeriodeDetail::findOrFail($req['payment_id']);
            $pembayaran->status = $req['status'];
            $pembayaran->save();
            if($req['status']=="Lunas"){
                //Pencatatan Pemasukan
                Pencatatan::create([
                    'id' => null,
                    'expense_id' => 0,
                    'payment_id' => $pembayaran->id,
                    'debit' => $req['nominal'],
                    'description' => $desc,
                    'kredit' => 0,
                ]);
            }
            return redirect()
                ->route('payment.monthly.show.detail',[$pembayaran->payment_id,$req['siswa']])
                ->with('success', 'Status disimpan!');
        }catch(Exception $e){
        return redirect()
            ->route('payment.monthly.show.detail',[$pembayaran->payment_id,$req['siswa']])
            ->with('error', 'Pembayaran gagal!');
        }
    }

    public function convertToBulan($id=1)
    {
        $bulan = ['',"Januari", "Februari", "Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
        return $bulan[$id];
    }
    /**
     * 
     * @return string tanggal dalam format dd/mm/yyyy
     */
    public function getTanggalHariIni()
    {
        $date = now();
        $date = explode(" ", $date);
        $date = $date[0];
        $date = explode("-", $date);
        $date = $date[2]."/".$date[1]."/".$date[0];
        return $date;
    }
    /**
     * 
     * @param string invalid format
     * @return string tanggal dalam format dd/mm/yyyy
     */
    public function convertToCorrectDateValue($date)
    {
        $date = explode("/", $date);
        $date = $date[2]."-".$date[1]."-".$date[0];
        return $date;
    }

    /**
     * Update status untuk pembayaran dengan kategori bayar per bulan
     */
    public function updateStatusBulanan(Request $request)
    {
        $req = $request->all();
        if ($req['status'] == 'Lunas')
        {
            if(!isset($req['calendar'])){
                return redirect()
                ->route('payment.monthly.show.detail',[$req['payment_id'],$req['student_id'],$req['category_id']])
                ->with('error', 'Tanggal Pembayaran Kosong!');
            }
        }
        if($req['status']=="Lunas"){   
            $user = Auth::user()->id;
            $penerima = Auth::user()->name;
            $category = FinancingCategory::findOrFail($req['category_id']);
            $student = Student::findOrFail($req['student_id']);
            $data = PaymentDetail::findOrFail($req['id']);
            $bulan = $this->convertToCorrectDateValue($req['calendar']);
            $desc = "Pembayaran {$category->nama} untuk periode {$data->bulan} dari {$student->nama} kelas {$student->kelas} ( {$student->major->nama} ) dibayar pada {$bulan} diterima oleh {$penerima}";
            $title = "{$category->nama} {$student->nama} periode {$data->bulan}";
            
            $data->user_id = $user;
            $data->tgl_dibayar = $bulan;
            $data->nominal = $req['nominal_bayar'];
            $data->status = $req['status'];
            $data->save();

            if($req['set_simpanan']=="1" && $req['gunakan_simpanan']=="1"){
                $student->simpanan = (intval($req['nominal']) - intval($req['nominal_bayar'])); 
            }elseif($req['set_simpanan']=="1"){
                $student->simpanan += (intval($req['nominal']) - intval($req['nominal_bayar'])); 
            }elseif($req['gunakan_simpanan']=="1"){
                $student->simpanan = 0;
            }
            $student->save();

            Income::create([
                'id' => null,
                'payment_detail_id' => $req['id'],
                'title' => $title,
                'description' => $desc,
                'sumber' => 'Siswa',
                'nominal' => $req['nominal_bayar']
            ]);

            $last_id = DB::getPdo()->lastInsertId();

            Pencatatan::create([
                'id' => null,
                'expense_id' => 0,
                'income_id' => $last_id,
                'payment_id' => $data->payment_id,
                'debit' => $req['nominal_bayar'],
                'description' => $desc,
                'kredit' => 0,
            ]);
        }else{
            $data = PaymentPeriodeDetail::findOrFail($req['id']);
            $data->status = $req['status'];
            $data->save();
        }
        return redirect()
        ->route('payment.monthly.show.detail',[$req['payment_id'],$req['student_id'],$req['category_id']])
        ->with('success', 'Periode pembayaran ditambah!');
    }

    /**
     * Menambah periode pembayaran dengan kategori bayar per bulan
     */
    public function addPeriodeBulanan(Request $request)
    {
        $req = $request->all();
        $status = "Waiting";
        PaymentPeriodeDetail::create([
            'payment_id' => $req['payment_id'],
            'payment_periode_id' => $req['periode'],
            'user_id' => 0,
            'status' => $status,
        ]);

        return redirect()
        ->route('payment.monthly.show.detail',[$req['payment_id'],$req['student_id'],$req['category_id']])
        ->with('success', 'Periode pembayaran ditambah!');
    }
    public function deletePeriodeBulanan($id)
    {
        $data = PaymentDetail::find($id);
        $payment_id = $data->payment_id;
        $category_id = $data->periode->financing_category_id;
        $student_id = $data->payment->student->id;

        $data->status = 'Waiting';
        $data->user_id = '0';
        $data->tgl_dibayar = null;
        $data->nominal = null;
        $data->save();

        Cicilan::where('payment_detail_id', $id)->delete();
        $income = Income::where('payment_detail_id', $id)->first();
        Pencatatan::where('income_id', $income->id)->delete();
        Income::where('payment_detail_id', $id)->delete();

        return redirect()
            ->route('payment.monthly.show.detail',[$payment_id,$student_id,$category_id])
            ->with('success', 'Periode pembayaran ditambah!');
    }
    public function deletePeriode($id)
    {
        //Get data detail payment
        $data = PaymentDetail::find($id);
        $payment_id = $data->payment_id;
        $category_id = $data->periode->financing_category_id;
        $student_id = $data->payment->student->id;
        
        //Ubah status payment dari tunai ke waiting
        $data_payment = Payment::find($payment_id);
        $data_payment->jenis_pembayaran = 'Waiting';
        $data_payment->jenis_potongan = 'persentase';
        $data_payment->persentase = 0;
        $data_payment->nominal_potongan = 0;
        $data_payment->save();
        
        //Hapus data pembayaran pada pencatatan
        $income = Income::where('payment_detail_id', $id)->first();
        Pencatatan::where('income_id', $income->id)->delete();
        Income::where('payment_detail_id', $id)->delete();
        Cicilan::where('payment_detail_id', $id)->delete();

        $data->status = "Waiting";
        $data->user_id = null;
        $data->nominal = null;
        $data->save();

        //Redirect ke halaman pembayaran berdasarkan kategori
        return redirect()
            ->route('payment.show', $category_id)
            ->with('success', 'Pembayaran dibatalkan!');
    }

    public function deleteCicilan($id, $payment){
        $status = "Waiting";
        $data_payment = Payment::find($payment);
        $details = PaymentDetail::where('payment_id', $payment)->get();
        
        foreach ($details as $detail) {
            $detail->status = $status;
            $detail->save();
        }

        $income = Income::where('cicilan_id', $id)->first();
        $pencatatan = Pencatatan::where('income_id', $income->id)->delete();
        $income->delete();
        
        $cicilan = Cicilan::find($id);
        
        $siswa = Student::find($data_payment->student_id);
        $siswa->simpanan = intval($siswa->simpanan) + intval($cicilan->simpanan);
        $siswa->save();

        Cicilan::find($id)->delete();
        
        //Redirect ke halaman pembayaran berdasarkan kategori
        return redirect()
            ->route('payment.details.cicilan', [$data_payment->financing_category_id, $data_payment->student_id, $data_payment->id])
            ->with('success', 'Cicilan dibatalkan!');
    }
}
