<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPExcel_Reader_CSV;
use PHPExcel_Style_NumberFormat;
use PHPExcel_Shared_Date;
use Carbon\Carbon;

class Actualizar69 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Anexo69:Actualizar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualizar tabla de anexo 69 desde la página del SAT';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', -1);
        echo "Comienza proceso de actualización de anexo 69".PHP_EOL;
        DB::table('69a')->truncate();

        /*
         * Leer excel descargado del SAT
         */
        //$csv_file = "http://www.sat.gob.mx/cifras_sat/Documents/Listado_Completo_69.csv";
        //$tmp_file = sys_get_temp_dir() . '/' . basename("http://www.sat.gob.mx/cifras_sat/Documents/Listado_Completo_69.csv");
        $csv_file = "http://omawww.sat.gob.mx/cifras_sat/Documents/Listado_Completo_69.csv";
        $tmp_file = sys_get_temp_dir() . '/' . basename("http://omawww.sat.gob.mx/cifras_sat/Documents/Listado_Completo_69.csv");
        if (!file_exists($tmp_file)) {
            shell_exec("wget -O $tmp_file $csv_file");
            if (file_exists($tmp_file)) {
                echo "Archivo descargado" . PHP_EOL;
            } else {
                throw new Exception("Ocurrio un error al descargar el archivo");
            }
        }

        echo "Cargando archivo " . basename($tmp_file) . "..." . PHP_EOL;
        $archivo = $tmp_file;
        $objReader = new PHPExcel_Reader_CSV;
        $objReader->setInputEncoding('windows-1252');
        $objPHPExcel = $objReader->load($archivo);

        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();

        /*Leer listado de la 69*/
        echo "Registrar datos del archivo de excel a la BD..." . PHP_EOL;
        for ($row = 2; $row <= $highestRow; $row++) {
            $rfc = $sheet->getCell("A".$row)->getFormattedValue();
            $razon_social = $sheet->getCell("B".$row)->getFormattedValue();
            $tipo_persona = $sheet->getCell("C".$row)->getFormattedValue();
            $supuesto = $sheet->getCell("D".$row)->getFormattedValue();
            $fecha_primera_publicacion = $sheet->getCell("E".$row)->getValue();
            if(@$fecha_primera_publicacion) {
                if (gettype($fecha_primera_publicacion) == 'double') {
                    $fecha_primera_publicacion = date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($fecha_primera_publicacion + 1));
                }
                else {
                    $fecha_primera_publicacion = Carbon::createFromFormat('d/m/Y', $fecha_primera_publicacion)->format('Y-m-d');
                }
            }
            $monto = $sheet->getCell("F".$row)->getFormattedValue();
            $fecha_publicacion = $sheet->getCell("G".$row)->getValue();
            if(@$fecha_publicacion) {
                if (gettype($fecha_publicacion) == 'double') {
                    $fecha_publicacion = date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($fecha_publicacion + 1));
                }
                else {
                    $fecha_publicacion = Carbon::createFromFormat('d/m/Y', $fecha_publicacion)->format('Y-m-d');
                }
            }

            if (strpos($razon_social, "\\") !== false) {
                $razon_social = str_replace("\\", "\\\\", $razon_social);
            }
            if (strpos($razon_social, "'") !== false) {
                $razon_social = str_replace("'", "\'", $razon_social);
            }

            if ($rfc != "XXXXXXXXXXXX") {
                DB::statement(" INSERT INTO `69a` (rfc, razon_social, tipo_persona, supuesto" . (@$fecha_primera_publicacion ? ',fecha_primera_publicacion' : '') .(@$monto ? ',monto' : ''). (@$fecha_publicacion ? ",fecha_publicacion" : "") . " ) VALUES ('$rfc','$razon_social','$tipo_persona','$supuesto'" . (@$fecha_primera_publicacion ? ",'$fecha_primera_publicacion'" : "") . (@$monto ? ",'$monto'" : ""). (@$fecha_publicacion ? ",'$fecha_publicacion'" : "") . ")");
            }
            $this->progressBar($row, $highestRow);
        }

        $Nuevo = 0;
        $Clone = 0;
        if(Schema::hasTable('69a_Clone')) {
            $Nuevo = DB::table('69a')->get()->count();
            $Clone = DB::table('69a_Clone')->get()->count();
            if($Nuevo == $Clone) {
                echo PHP_EOL ."No existen cambios en el archivo de excel" . PHP_EOL;
                $email_data = array(
                    'recipient' => ['cmiguel@advans.mx'],
                    'subject' => 'Sin actualización de 69'
                );

                Mail::send('layouts.anexo', ['antes' => $Clone, 'despues' => $Nuevo ,'content' => 'No hubo cambios en el listado 69'], function ($message) use ($email_data) {
                    $message->from('cmiguel@advans.mx', 'Laravel')
                        ->to($email_data['recipient'])
                        ->subject($email_data['subject']);
                });
                unlink($tmp_file);
                exit();
            }else{
                echo PHP_EOL . "Registros anteriores: " . $Clone . PHP_EOL;
                echo "Registros nuevos: " . $Nuevo . PHP_EOL;
                DB::statement("DROP TABLE `69a_Clone`");
            }
        }

        echo PHP_EOL . "Registrar tabla para comparar" . PHP_EOL;
        DB::statement("CREATE TABLE `69a_Clone` AS SELECT * FROM `69a`");

        echo  PHP_EOL . "Proceso terminado..." . PHP_EOL;
        $email_data = array(
            'recipient' => ['miguel_caballero92@hotmail.com','cmiguel@advans.mx'],
            'subject' => 'Actualización de 69'
        );
        $dump_sql = sys_get_temp_dir() . '/anexo69a_'.date('Ymd').'.sql';
        shell_exec('mysqldump anexo69 69a > '. $dump_sql);

        Mail::send('layouts.anexo', ['antes' => $Clone, 'despues' => $Nuevo ,'content' => 'Hubo actualización de anexo 69'], function ($message) use ($email_data, $dump_sql) {
            $message->from('cmiguel@advans.mx', 'Laravel')
                ->to($email_data['recipient'])
                ->subject($email_data['subject']);
                //->attach($dump_sql);
        });
        unlink($tmp_file);
        unlink($dump_sql);
    }

    /*
    * Metodo para extraer número de oficio
    */
    function extraer_oficio($var_string)
    {
        $result = "";
        if (preg_match("/\b\S([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)/", $var_string, $matches)) {
            $result = $matches[0];
        } else {
            if (preg_match("/\b([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)/", $var_string, $matches)) {
                $result = $matches[0];
            } else {
                if (preg_match("/\b\S([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)/", $var_string, $matches)) {
                    $result = $matches[0];
                } else {
                    if (preg_match("/\b\S([0-9]+)-([0-9]+)-([0-9]+)- ([0-9]+)/", $var_string, $matches)) {
                        $result = str_replace('- ', '-', $matches[0]);
                    } else {
                        if (preg_match("/\b\S([0-9]+)-([0-9]+)-([0-9]+)/", $var_string, $matches)) {
                            $result = $matches[0];
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function progressBar($done, $total) {
        $perc = floor(($done / $total) * 100);
        $left = 100 - $perc;
        $write = sprintf("\033[0G\033[2K[%'={$perc}s>%-{$left}s] - $perc%% - $done/$total", "", "");
        fwrite(STDERR, $write);
    }
}
