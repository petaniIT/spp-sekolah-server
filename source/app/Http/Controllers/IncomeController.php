<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;
use App\Income;
use App\Pencatatan;
use Illuminate\Support\Facades\Session;

class IncomeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $datas = Income::orderBy('id', 'desc')->get();
        $no=1;
        $bulan = Income::selectRaw('MONTH(updated_at) AS bulan')
                ->groupBy('bulan')
                ->orderBy('bulan')
                ->get();
        $tahun = Income::selectRaw('YEAR(updated_at) AS tahun')
                ->groupBy('tahun')
                ->orderBy('tahun')
                ->get();
        $report['bulan'] = "";
        $report['tahun'] = "";
        return view('pemasukan.index', compact('datas','no','bulan','tahun', 'report'));
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
        $req = $request->all();
        try {
            $sql_date = $this->convertDateToSQLDate($request->tanggal);
            $req['tanggal'] = $sql_date;
            $uuid = Uuid::uuid1();
            // menyimpan data file yang diupload ke variabel $file
            $file = $request->file('foto');
            
            $nama_file = time()."_".$file->getClientOriginalName();
    
            // isi dengan nama folder tempat kemana file diupload
            $tujuan_upload = 'nota';
            $file->move($tujuan_upload,$uuid.$nama_file);
            
            Income::create([
                'id' => null,
                'updated_at' => $req['tanggal'],
                'title' => $req['title'],
                'description' => $req['description'],
                'sumber' => $req['sumber' ],
                'nominal' => $req['nominal'],
                'foto' => $uuid.$nama_file,
            ]);
            $id = DB::getPdo()->lastInsertId();
            $desc = "Pemasukan {$req['title']} dari {$req['sumber']}";
            Pencatatan::create([
                'id' => null,
                'income_id' =>$id,
                'expense_id' =>0,
                'debit' => $req['nominal'],
                'description' => $desc,
                'kredit' => 0,
                'updated_at' =>$req['tanggal'],
            ]);
          return redirect()
              ->route('income.index')
              ->with('success', 'Data Pemasukan berhasil disimpan!');

        }catch(Exception $e){
          return redirect()
              ->route('income.create')
              ->with('success', 'Data pengeluaran gagal disimpan!');
        }
    }

    public function filter(Request $request)
    {
        
        if(isset($request->bulan) && isset($request->tahun)){
            $datas = Income::orderBy('id', 'desc')
                    ->whereMonth('updated_at','=',$request->bulan)
                    ->whereYear('updated_at','=',$request->year)
                    ->get();
        }
        elseif(isset($request->tahun)){
            $datas = Income::orderBy('id', 'desc')
                    ->whereYear('updated_at','=',$request->year)
                    ->get();
        }
        elseif(isset($request->bulan)){
            $datas = Income::orderBy('id', 'desc')
                    ->whereMonth('updated_at','=',$request->bulan)
                    ->get();
        }else{
            return redirect()
                    ->route('income.index')
                    ->with('error','Pilihan data kosong');
        }

        $no=1;
        $bulan = Income::selectRaw('MONTH(updated_at) AS bulan')
                ->groupBy('bulan')
                ->orderBy('bulan')
                ->get();
        $tahun = Income::selectRaw('YEAR(updated_at) AS tahun')
                ->groupBy('tahun')
                ->orderBy('tahun')
                ->get();
        $report['bulan'] = $request->bulan;
        $report['tahun'] = $request->tahun;
        return view('pemasukan.index', compact('datas','no','bulan','tahun', 'report'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        try {
            $req = $request->all();
            $sql_date = $this->convertDateToSQLDate($request->tanggal);
            $req['tanggal'] = $sql_date;
            // echo '<pre>';
            // var_dump($req);die;
            $data = Income::findOrFail($id);
            if ($request->file('foto')!='') {
                $file = $request->file('foto');
                $nama_file = time()."_".$file->getClientOriginalName();
                $tujuan_upload = 'nota';
                $file->move($tujuan_upload,$nama_file);
                $data->foto = $nama_file;
                
              }
            $data->title = $req['title'];
            $data->description = $req['description'];
            $data->sumber = $req['sumber'];
            $data->nominal = $req['nominal'];
            $data->updated_at = $req['tanggal'];
            $data->save();

            $desc = "Pemasukan {$req['title']} dari {$req['sumber']}";

            $jur = DB::table('pencatatans')
            ->where('income_id', $id)
            ->update([
                'debit' => $req['nominal'],
                'description' => $desc,
                'updated_at' => $req['tanggal']
            ]);

          return redirect()
              ->route('income.index')
              ->with('success', 'Data pemasukan berhasil diubah!');

        } catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
          return redirect()
              ->route('income.index')
              ->with('error', 'Data pemasukan gagal diubah!');
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
            Income::findOrFail($id)->delete();
            DB::table('pencatatans')
            ->where('income_id', $id)
            ->delete();
            return redirect()
                ->route('income.index')
                ->with('success', 'Data pemasukan berhasil dihapus!');
  
          } catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){
            return redirect()
                ->route('income.index')
                ->with('error', 'Data pemasukan gagal dihapus!');
          }
    }
    public function convertDateToSQLDate($date)
    {
        $temp = explode("/",$date);
        return $temp[2]."-".$temp[0]."-".$temp[1];
    }
}