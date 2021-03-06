<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DoctorController;
use App\Cita;
use DB;
use Illuminate\Http\Request;
use App\Mail\citasEmail;
use Illuminate\Support\Facades\Mail;

class CitaController extends Controller
{


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {

        $date = date("Y-m-d");   

        $infos =  DB::select('SELECT d.id as id_doc, c.fecha as fecha, p.nombre as nombre_paciente, p.id as id, d.nombre as nombre,c.motivo as motivo 
                            FROM doctors d, patients p, citas c 
                            WHERE ((c.id_paciente = ?) AND (p.id = ?) AND (c.id_doctor = d.id) AND (c.fecha >= ?))
                            ORDER BY c.fecha ASC ',[$id,$id,$date]);

        if (empty($infos)){
          return view('layouts.users.nocita',['date'=>$date,'id' => $id]);
        } else {
            return view('layouts.users.citas',['infos' => $infos, 'id' => $id, 'date' => $date]);
        } 
        
    }

        public function indexAdmin($id)
    {
        $date = date("Y-m-d");   

        $admin = self::checkAdmin($id);

        $infos =  DB::select('SELECT d.id as id_doc, c.fecha as fecha, p.nombre as nombre_paciente, p.id as id, d.nombre as nombre,c.motivo as motivo 
                            FROM doctors d, patients p, citas c 
                            WHERE ((d.id = ?) AND (c.id_doctor = ?) AND (c.fecha >= ?) AND (p.id= c.id_paciente))
                            ORDER BY c.fecha ASC ',[$id,$id,$date]);
        if (empty($infos)){
          return view('layouts.admin.nocita',['date'=>$date,'id' => $id,'administrador' => $admin]);
        } else {
            return view('layouts.admin.citas',['infos' => $infos, 'id' => $id, 'date' => $date,'administrador' => $admin]);
        } 
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
            if ($request->has('admin')){
                $data['id_paciente'] = $request['id_paciente'];
                $data['id_doctor'] = $request['id_doctor'];
                $data['motivo'] = $request['motivo'];
                $data['hora'] =$request['hora'];


                $info = $request->all();

                $date = $info['fecha'];

                $data['fecha'] = date("Y-m-d", strtotime($date));

                $cita = Cita::create($data);

                $paciente = DB::table('patients')->select('correo','nombre')->where('id',$data['id_paciente'])->get();
                $doctor = DB::table('doctors')->select('nombre')->where('id',$data['id_doctor'])->get();

               //Mail::to($paciente[0]->correo)->send(new citasEmail($data['fecha'],$doctor[0]->nombre,$paciente[0]->nombre,$data['hora']));

                return redirect()->action('CitaController@indexAdmin',['id' => $request->id_user]);

            }else {

                $data['id_paciente'] = $request['id_paciente'];
                $data['id_doctor'] = $request['id_doctor'];
                $data['motivo'] = $request['motivo'];
                $data['hora'] =$request['hora'];

                $info = $request->all();

                $date = $info['fecha'];

                $data['fecha'] = date("Y-m-d", strtotime($date));

                $cita = Cita::create($data);

                $paciente = DB::table('patients')->select('correo','nombre')->where('id',$data['id_paciente'])->get();

                $doctor = DB::table('doctors')->select('nombre','horario')->where('id',$data['id_paciente'])->get();

//Mail::to($paciente[0]->correo)->send(new citasEmail($data['fecha'],$doctor[0]->nombre,$paciente[0]->nombre,$data['hora']));

                return redirect()->action('CitaController@index',['id' => $request->id_paciente]);
            }

    }

    public function confirmCita(Request $request, $id)
    {

        $date = $request->fecha;

        $date = date("Y-m-d", strtotime($date));

        $request->fecha = $date;

        $cupos = DB::select('SELECT * from citas WHERE ((fecha = ?) AND (id_doctor = ?))', [$request->fecha,$request->id_doctor]);

        $cupos = count($cupos);

        $doctor = DB::table('doctors')->join('especialidades','doctors.id_especialidad', '=','especialidades.id')->select('doctors.id','doctors.nombre','doctors.apellido','doctors.horario','doctors.pacientes_dia','especialidades.nombre as especialidad')->where('doctors.id',$request->id_doctor)->get();

        $disponibles = $doctor[0]->pacientes_dia - $cupos;

        $numero = $cupos + 1;

        $paciente = DB::table('patients')->select('nombre','id')->where('id',$request->id_paciente)->get();

        $exist = self::checkExist($request);

        if ($exist){
            if ($request->has('admin')){
                    $patients = DB::table('patients')->select('id','nombre','cedula')->orderBy('nombre', 'asc')->get();
                    $doctors = DB::table('doctors')->join('especialidades','doctors.id_especialidad', '=','especialidades.id')->select('doctors.id','doctors.nombre','doctors.apellido','doctors.admin','especialidades.nombre as especialidad')->get();

                    return view('layouts.admin.citas-add',['cupos' => $disponibles,'info' => $request, 'id' => $id,'administrador' => $request->admin,'doctor' => $doctor[0],'paciente' => $paciente[0]->nombre,'patients' => $patients, 'puesto' => $numero,'doctors' => $doctors]);
            }else{
                   $doctors = DB::table('doctors')->join('especialidades','doctors.id_especialidad', '=','especialidades.id')->select('doctors.id','doctors.nombre','doctors.apellido','doctors.horario','doctors.pacientes_dia','especialidades.nombre as especialidad')->where('doctors.id',$request->id_doctor)->get();

                    return view('layouts.users.citas-add',['cupos' => $disponibles,'info' => $request, 'id' => $id,'doctor' => $doctor[0],'patient' => $paciente,'doctors' => $doctors,'puesto' => $numero]);
            }
        }else{
            $mensaje = 'No puede solicitar citas con un doctor dos veces en un día';
            if ($request->has('admin')){
                    $patients = DB::table('patients')->select('id','nombre')->orderBy('nombre', 'asc')->get();
                    $doctors = DB::table('doctors')->join('especialidades','doctors.id_especialidad', '=','especialidades.id')->select('doctors.id','doctors.nombre','doctors.apellido','doctors.admin','especialidades.nombre as especialidad')->get();

                    return view('layouts.admin.citas-add',['cupos' => $disponibles,'info' => $request, 'id' => $id,'administrador' => $request->admin,'doctor' => $doctor[0],'paciente' => $paciente[0]->nombre,'patients' => $patients,'mensaje' => $mensaje,'puesto' => $numero]);
            }else{
                    $doctors = DB::table('doctors')->join('especialidades','doctors.id_especialidad', '=','especialidades.id')->select('doctors.id','doctors.nombre','doctors.apellido','doctors.horario','doctors.pacientes_dia','especialidades.nombre as especialidad')->where('doctors.id',$request->id_doctor)->get();


                    return view('layouts.users.citas-add',['cupos' => $disponibles,'info' => $request, 'id' => $id,'doctor' => $doctor[0],'patient' => $paciente,'doctors' => $doctors,'mensaje' => $mensaje,'puesto' => $numero]);
            }
        }    

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($date,$id,$id_doc)
    {

        DB::Delete('DELETE FROM citas where ((fecha = ?) AND (id_paciente = ?) AND (id_doctor = ?))',[$date,$id,$id_doc]);

        return redirect()->route('index',['id' => $id]);

    }

        public function Adestroy($date,$id,$id_doc)
    {

        $result = DB::Delete('DELETE FROM citas where ((fecha = ?) AND (id_paciente = ?) AND (id_doctor = ?))',[$date,$id,$id_doc]);

        return redirect()->route('admin',['id' => $id_doc]);

    }

    public function datoCita($id){

        $patient = DB::table('patients')->select('id','nombre')->where('id',$id)->get();
        $doctors = DB::table('doctors')->join('especialidades','doctors.id_especialidad', '=','especialidades.id')->select('doctors.id','doctors.nombre','doctors.apellido','especialidades.nombre as especialidad')->orderBy('nombre', 'asc')->get();

        return view('layouts.users.citas-add', ['patient' => $patient, 'doctors' => $doctors, 'id' => $id]);
    }

        public function adatoCita($id){

        $admin = self::checkAdmin($id);    

        $patients = DB::table('patients')->select('id','nombre')->orderBy('nombre', 'asc')->get();
        $doctors = DB::table('doctors')->join('especialidades','doctors.id_especialidad', '=','especialidades.id')->select('doctors.id','doctors.nombre','doctors.apellido','doctors.admin','especialidades.nombre as especialidad')->get();
        $doctor = DB::table('doctors')->join('especialidades','doctors.id_especialidad', '=','especialidades.id')->select('doctors.id','doctors.nombre','doctors.apellido','doctors.admin','especialidades.nombre as especialidad')->where('doctors.id',$id)->get();

        return view('layouts.admin.citas-add', ['patients' => $patients,'doctor' => $doctor[0], 'doctors' => $doctors, 'id' => $id,'administrador' =>$admin]);
    }


    public function citaByDia(Request $request){

        $patient = DB::table('patients')->select('id')->where('id',$request->id)->get();
        $date = date("Y-m-d", strtotime($request->fecha));

        $admin = self::checkAdmin($request->id); 

        $infos =  DB::select('SELECT c.fecha as fecha, p.nombre as nombre_paciente, p.id as id, d.nombre as nombre,c.motivo as motivo 
                            FROM doctors d, patients p, citas c 
                            WHERE ((p.id = ? ) AND (c.fecha >= ?))',[$request->id,$date]);
        if (empty($infos)){
          return view('layouts.admin.nocita',['date'=>$date,'id' => $request->id,'administrador' =>$admin]);
        } else {
            return view('layouts.admin.citas',['infos' => $infos, 'id' => $request->id, 'date' => $date,'administrador' =>$admin]);
        } 


    }

    public function checkAdmin($id){

        $admin = DB::table('doctors')->select('admin')->where('id',$id)->get();

        return $admin[0]->admin;
    }

    public function checkExist(Request $request){

        $cita = DB::select('SELECT * from citas WHERE ((fecha = ?) AND (id_paciente = ?) AND (id_doctor = ?))', [$request->fecha,$request->id_paciente,$request->id_doctor]);


        if (count($cita) == 0){
            return true;
        }else{
            return false;
        }

    }        

}
