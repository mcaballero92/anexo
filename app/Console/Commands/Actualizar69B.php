<?php

namespace App\Console\Commands;

use DOMDocument;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPExcel_IOFactory;

class Actualizar69B extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Anexo69B:Actualizar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualizar tabla de anexo 69B desde la página del SAT';

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
        echo "Comienza proceso de actualización de anexo 69B".PHP_EOL;
        DB::table('69')->truncate();
        /*
         * Leer html del SAT
         */
        $texto = "";
        $url = fopen("https://www.gob.mx/sat/acciones-y-programas/notificacion-a-contribuyentes-con-operaciones-presuntamente-inexistentes-y-listados-definitivos-131644", "r");
        if ($url) {
            while (! feof($url)) {
                $texto .= fgets($url, 512);
            }
        }
        echo "Leyendo url https://www.gob.mx/sat/acciones-y-programas/notificacion-a-contribuyentes-con-operaciones-presuntamente-inexistentes-y-listados-definitivos-131644" . PHP_EOL;
        $DOM = new DOMDocument();
        @$DOM->loadHTML($texto);
        echo "Leyendo página del SAT en buscar de las url de oficio y anexo..." . PHP_EOL;
        $Table = $DOM->getElementsByTagName('table');
        $Presunto = [];
        $Definitivo = [];
        $Desvirtuado = [];
        $Sentencia = [];
        $Tabla = 1;
        $Columnas = [];
        foreach ($Table as $Node) {
            if ($Node->getElementsByTagName('td')->length > 0) {
                $Detail = $Node->getElementsByTagName('td');
                $j = 0;
                foreach ($Detail as $sNodeDetail) {
                    $item0 = $sNodeDetail->getElementsByTagName('a')->item(0);
                    $item1 = $sNodeDetail->getElementsByTagName('a')->item(1);
                    if ($sNodeDetail->getElementsByTagName('a')->length > 0) {
                        if (@$item0) {
                            $Columnas['url_oficio'] = $item0->getAttribute('href');
                            $Columnas['oficio'] = $this->extraer_oficio($item0->nodeValue);
                            //$Columnas['oficio'] = $item0->getAttribute('title');
                            /*if (strlen($Columnas['oficio']) < 14 || strlen($Columnas['oficio']) == 0) {
                                $Columnas['oficio'] = $item0->nodeValue;
                            }
                            if ($this->extraer_oficio($Columnas['oficio']) === "500-05-20172605") {
                                $Columnas['oficio'] = $item0->nodeValue;
                            }
                            $Columnas['oficio'] = $this->extraer_oficio($Columnas['oficio']);*/
                        }
                        if (@$item1) {
                            if ($Tabla != 3) {
                                $Columnas['url_anexo'] = $item1->getAttribute('href');
                                $Columnas['anexo'] = $item1->getAttribute('title');
                                if (strlen($Columnas['anexo']) == 7 || strlen($Columnas['anexo']) == 0) {
                                    $Columnas['anexo'] = $item1->nodeValue;
                                }
                            }
                        }
                    }
                    if ($j == 2 && ($Tabla == 1 || $Tabla == 2)) {
                        switch ($Tabla) {
                            case 1:
                                if (count($Columnas) > 0) {
                                    array_push($Presunto, $Columnas);
                                }
                                break;
                            case 2:
                                if (count($Columnas) > 0) {
                                    array_push($Definitivo, $Columnas);
                                }
                                break;
                        }
                        $j = 0;
                    } elseif ($j == 1 && $Tabla == 3) {
                        if (count($Columnas) > 0) {
                            array_push($Desvirtuado, $Columnas);
                        }
                        $j = 0;
                    } elseif ($j == 1 && $Tabla == 4) {
                        if (count($Columnas) > 0) {
                            array_push($Sentencia, $Columnas);
                        }
                        $j = 0;
                    } else {
                        $j++;
                    }
                }
                $Tabla++;
            }
        }

        $Columnas = [];
        foreach ($Table as $Node) {
            if ($Node->getElementsByTagName('th')->length > 0) {
                $Detail = $Node->getElementsByTagName('th');
                foreach ($Detail as $sNodeDetail) {
                    $item0 = $sNodeDetail->getElementsByTagName('a')->item(0);
                    $item1 = $sNodeDetail->getElementsByTagName('a')->item(1);
                    if ($sNodeDetail->getElementsByTagName('a')->length > 0) {
                        if (@$item0) {
                            $Columnas['url_oficio'] = 'http://omawww.sat.gob.mx'.$item0->getAttribute('href');
                            $Columnas['oficio'] = $this->extraer_oficio($item0->nodeValue);
                        }
                        if (@$item1) {
                            $Columnas['url_anexo'] = 'http://omawww.sat.gob.mx' . $item1->getAttribute('href');
                            $Columnas['anexo'] = $item1->getAttribute('title');
                            if (strlen($Columnas['anexo']) == 7 || strlen($Columnas['anexo']) == 0) {
                                $Columnas['anexo'] = $item1->nodeValue;
                            }
                        }
                    }
                    if (count($Columnas) > 0) {
                        array_push($Sentencia, $Columnas);
                    }
                }
            }
        }

        /*
         * Leer excel descargado del SAT
         */
        //$csv_file = "http://www.sat.gob.mx/cifras_sat/Documents/Listado_Completo_69-B.csv";
        //$tmp_file = sys_get_temp_dir() . '/' . basename("http://www.sat.gob.mx/cifras_sat/Documents/Listado_Completo_69-B.csv");
        $csv_file = "http://omawww.sat.gob.mx/cifras_sat/Documents/Listado_Completo_69-B.csv";
        $tmp_file = sys_get_temp_dir() . '/' . basename("http://omawww.sat.gob.mx/cifras_sat/Documents/Listado_Completo_69-B.csv");
        if (!file_exists($tmp_file)) {
            shell_exec("wget -O $tmp_file $csv_file");
            if (file_exists($tmp_file)) {
                echo "Archivo descargado" . PHP_EOL;
            } else {
                throw new Exception("Ocurrio un error al descargar el archivo");
            }
        }

        echo "Cargando archivo " . basename($tmp_file) . "..." . PHP_EOL;
        $archivo = $tmp_file;// Container::getInstance()->resourcePath( "files/Listado_Completo_69-B.csv");//"Listado_Completo_69-B02112017.xlsx";
        $inputFileType = PHPExcel_IOFactory::identify($archivo);
        $objReader = new \PHPExcel_Reader_CSV();
        $objReader->setInputEncoding('windows-1252');
        $objPHPExcel = $objReader->load($archivo);

        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        /*Leer listado de la 69B*/
        echo "Registrar datos del archivo de excel a la BD..." . PHP_EOL;
        for ($row = 4; $row <= $highestRow; $row++) {
            $rfc = $sheet->getCell("B".$row)->getFormattedValue();
            $contribuyente = $sheet->getCell("C".$row)->getFormattedValue();
            $tipo = $sheet->getCell("D".$row)->getValue();

            if (strpos($contribuyente, "'") !== false) {
                $contribuyente = str_replace("'", "\'", $contribuyente);
            }
            if (strpos($contribuyente, '?') !== false) {
                $contribuyente = str_replace('?', '""', $contribuyente);
            }

            if ($rfc != "XXXXXXXXXXXX") {
                if ($sheet->getCell("E".$row)->getValue() != "") {
                    DB::statement(" INSERT INTO `69` (rfc, contribuyente, tipo) VALUES ('".$rfc."','".$contribuyente."','Presunto')");
                }
                if ($sheet->getCell("L".$row)->getValue() != "") {
                    DB::statement(" INSERT INTO `69` (rfc, contribuyente, tipo) VALUES ('".$rfc."','".$contribuyente."','Definitivo')");
                }
                if ($sheet->getCell("J".$row)->getValue() != "") {
                    DB::statement(" INSERT INTO `69` (rfc, contribuyente, tipo) VALUES ('".$rfc."','".$contribuyente."','Desvirtuado')");
                }
                if ($sheet->getCell("P" . $row)->getValue() != "") {
                    DB::statement(" INSERT INTO `69` (rfc, contribuyente, tipo) VALUES ('".$rfc."','".$contribuyente."','Sentencia favorable')");
                }

                /*Leer Presuntos*/
                $oficio_excel = $sheet->getCell("E".$row)->getValue();
                $fecha_sat = $sheet->getCell("F".$row)->getValue();
                $fecha_dof = $sheet->getCell("H".$row)->getValue();

                $data = [];
                $columnas_datos = "";
                if (@$fecha_sat) {
                    $array_fecha = date_parse_from_format("j/n/Y", $fecha_sat);
                    $fecha_sat = $array_fecha['year'].'-'.$array_fecha['month'].'-'.$array_fecha['day'];
                    $columnas_datos = $columnas_datos.", fecha_sat='".$fecha_sat."'";
                    $data['fecha_sat'] = $fecha_sat;
                }
                if (@$fecha_dof) {
                    $array_fecha = date_parse_from_format("j/n/Y", $fecha_dof);
                    $fecha_dof = $array_fecha['year'].'-'.$array_fecha['month'].'-'.$array_fecha['day'];
                    $columnas_datos = $columnas_datos.", fecha_dof='".$fecha_dof."'";
                    $data['fecha_dof'] = $fecha_dof;
                }
                if (preg_match("/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+) /", $oficio_excel, $matches)) {
                    $oficio_excel = $matches[0];
                } else {
                    if (preg_match("/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+) /", $oficio_excel, $matches)) {
                        $oficio_excel = $matches[0];
                    }
                }
                $data['oficio'] = $oficio_excel;
                DB::statement("UPDATE `69` SET oficio='".$oficio_excel."'$columnas_datos WHERE rfc='".$rfc."' AND tipo='Presunto'");

                /*Leer Definitivos*/
                $oficio_excel = $sheet->getCell("L".$row)->getValue();
                $fecha_sat = $sheet->getCell("M".$row)->getValue();
                $fecha_dof = $sheet->getCell("N".$row)->getValue();
                $data = [];
                $columnas_datos = "";
                if (@$fecha_sat) {
                    $array_fecha = date_parse_from_format("j/n/Y", $fecha_sat);
                    $fecha_sat = $array_fecha['year'].'-'.$array_fecha['month'].'-'.$array_fecha['day'];
                    $columnas_datos = $columnas_datos.", fecha_sat='".$fecha_sat."'";
                    $data['fecha_sat'] = $fecha_sat;
                }
                if (@$fecha_dof) {
                    $array_fecha = date_parse_from_format("j/n/Y", $fecha_dof);
                    $fecha_dof = $array_fecha['year'].'-'.$array_fecha['month'].'-'.$array_fecha['day'];
                    $columnas_datos = $columnas_datos.", fecha_dof='".$fecha_dof."'";
                    $data['fecha_dof'] = $fecha_dof;
                }
                if (preg_match("/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+) /", $oficio_excel, $matches)) {
                    $oficio_excel = $matches[0];
                } else {
                    if (preg_match("/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+) /", $oficio_excel, $matches)) {
                        $oficio_excel = $matches[0];
                    }
                }
                $data['oficio'] = $oficio_excel;
                DB::statement("UPDATE `69` SET oficio='".$oficio_excel."'$columnas_datos WHERE rfc='".$rfc."' AND tipo='Definitivo'");

                /*Leer desvirtuados*/
                $oficio_excel = $sheet->getCell("J".$row)->getValue();
                $tipo_fecha_sat = $sheet->getCell("I".$row)->getDataType();
                $tipo_fecha_dof = $sheet->getCell("K".$row)->getDataType();
                $fecha_sat = $sheet->getCell("I".$row)->getValue();
                $fecha_dof = $sheet->getCell("K".$row)->getValue();

                $data = [];
                $columnas_datos = "";
                if (@$fecha_sat && $tipo_fecha_sat != 'n') {
                    $array_fecha = date_parse_from_format("j/n/Y", $fecha_sat);
                    $fecha_sat = $array_fecha['year'].'-'.$array_fecha['month'].'-'.$array_fecha['day'];
                    $columnas_datos = $columnas_datos.", fecha_sat='".$fecha_sat."'";
                    $data['fecha_sat'] = $fecha_sat;
                } elseif (@$fecha_sat && $tipo_fecha_sat == 'n') {
                    $fecha_sat = $sheet->getCell("I".$row)->getFormattedValue();
                    $fecha_sat = date('Y-m-d', \PHPExcel_Shared_Date::ExcelToPHP($fecha_sat + 1));
                    $columnas_datos = $columnas_datos.", fecha_sat='".$fecha_sat."'";
                    $data['fecha_sat'] = $fecha_sat;
                    \Log::info($columnas_datos);
                    \Log::info('3'.$tipo_fecha_sat);
                }
                if (@$fecha_dof && $tipo_fecha_dof != 'n') {
                    $array_fecha = date_parse_from_format("j/n/Y", $fecha_dof);
                    $fecha_dof = $array_fecha['year'].'-'.$array_fecha['month'].'-'.$array_fecha['day'];
                    $columnas_datos = $columnas_datos.", fecha_dof='".$fecha_dof."'";
                    $data['fecha_dof'] = $fecha_dof;
                } elseif (@$fecha_dof && $tipo_fecha_dof == 'n') {
                    $fecha_dof = $sheet->getCell("K".$row)->getFormattedValue();
                    $fecha_dof = date('Y-m-d', \PHPExcel_Shared_Date::ExcelToPHP($fecha_dof + 1));
                    $columnas_datos = $columnas_datos.", fecha_dof='".$fecha_dof."'";
                    $data['fecha_dof'] = $fecha_dof;
                }
                if (preg_match("/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+) /", $oficio_excel, $matches)) {
                    $oficio_excel = $matches[0];
                } else {
                    if (preg_match("/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+) /", $oficio_excel, $matches)) {
                        $oficio_excel = $matches[0];
                    }
                }
                $data['oficio'] = $oficio_excel;
                DB::statement("UPDATE `69` SET oficio='".$oficio_excel."'$columnas_datos WHERE rfc='".$rfc."' AND tipo='Desvirtuado'");

                /*Leer Sentencia favorable*/
                $oficio_excel = $sheet->getCell("O".$row)->getValue();
                $fecha_sat = $sheet->getCell("P".$row)->getValue();
                $fecha_dof = $sheet->getCell("R".$row)->getValue();
                $data = [];
                $columnas_datos = "";
                if (@$fecha_sat) {
                    $array_fecha = date_parse_from_format("j/n/Y", $fecha_sat);
                    $fecha_sat = $array_fecha['year'].'-'.$array_fecha['month'].'-'.$array_fecha['day'];
                    $columnas_datos = $columnas_datos.", fecha_sat='".$fecha_sat."'";
                    $data['fecha_sat'] = $fecha_sat;
                }
                if (@$fecha_dof) {
                    $array_fecha = date_parse_from_format("j/n/Y", $fecha_dof);
                    $fecha_dof = $array_fecha['year'].'-'.$array_fecha['month'].'-'.$array_fecha['day'];
                    $columnas_datos = $columnas_datos.", fecha_dof='".$fecha_dof."'";
                    $data['fecha_dof'] = $fecha_dof;
                }
                if (preg_match("/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+) /", $oficio_excel, $matches)) {
                    $oficio_excel = $matches[0];
                } else {
                    if (preg_match("/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+) /", $oficio_excel, $matches)) {
                        $oficio_excel = $matches[0];
                    }
                }
                $data['oficio'] = $oficio_excel;
                DB::statement("UPDATE `69` SET oficio='".$oficio_excel."'$columnas_datos WHERE rfc='".$rfc."' AND tipo='Sentencia favorable'");
            }
            $this->progressBar($row, $highestRow);
        }

        $Nuevo = 0;
        $Clone = 0;
        if(Schema::hasTable('69_Clone')) {
            $Nuevo = DB::table('69')->get()->count();
            $Clone = DB::table('69_Clone')->get()->count();
            if($Nuevo == $Clone) {
                echo PHP_EOL ."No existen cambios en el archivo de excel" . PHP_EOL;
                $email_data = array(
                    'recipient' => ['cmiguel@advans.mx'],
                    'subject' => 'Sin actualización de 69B'
                );

                Mail::send('layouts.anexo', ['antes' => $Clone, 'despues' => $Nuevo, 'content' => 'No hubo cambios en el listado 69B'], function ($message) use ($email_data) {
                    $message->from('cmiguel@advans.mx', 'Laravel')
                        ->to($email_data['recipient'])
                        ->subject($email_data['subject']);
                });

                if(DB::table('anexos_updates')->where('tipo', '69')->exists()) {
                    DB::statement("UPDATE `anexos_updates` SET fecha=NOW() WHERE tipo='69'");
                } else {
                    DB::statement("INSERT INTO `anexos_updates` VALUES ('69', NOW())");
                }

                unlink($tmp_file);
                exit();
            }else{
                echo PHP_EOL . "Registros anteriores: " . $Clone . PHP_EOL;
                echo "Registros nuevos: " . $Nuevo . PHP_EOL;
                DB::statement("TRUNCATE TABLE `69_Clone`");
                //DB::statement("DROP TABLE `69_Clone`");
            }
        }


        echo PHP_EOL . "Actualizar url de oficio y anexo..." . PHP_EOL;
        $result = DB::table("69")->orderBy('tipo','DESC')->get();
        $result = json_decode($result, true);

        if(count($result) > 0){
            $total = count($result);
            foreach($result as $index => $row){
                if ($row["tipo"] == "Presunto") {
                    /*Presuntos*/
                    foreach ($Presunto as $row_2) {
                        $oficio = trim($row_2['oficio']);
                        $pos = strpos($row["oficio"], $oficio);
                        if ($pos !== false) {
                            $url_oficio = $row_2['url_oficio'];
                            $url_anexo = $row_2['url_anexo'];
                            DB::statement("UPDATE `69` SET oficio='".$oficio."',url_oficio='".$url_oficio."',url_anexo='".$url_anexo."' WHERE rfc='".$row["rfc"]."' AND tipo='".$row["tipo"]."'");
                            break;
                        }
                    }
                }
                /*Definitivos*/
                if ($row["tipo"] == "Definitivo") {
                    foreach ($Definitivo as $row_2) {
                        $oficio = trim($row_2['oficio']);
                        $pos = strpos($row["oficio"], $oficio);
                        if ($pos !== false) {
                            $url_oficio = $row_2['url_oficio'];
                            $url_anexo = $row_2['url_anexo'];
                            DB::statement("UPDATE `69` SET oficio='".$oficio."',url_oficio='".$url_oficio."',url_anexo='".$url_anexo."' WHERE rfc='".$row["rfc"]."' AND tipo='".$row["tipo"]."'");
                            break;
                        }
                    }
                }

                /*Desvirtuados*/
                if ($row["tipo"] == "Desvirtuado") {
                    foreach ($Desvirtuado as $row_2) {
                        $oficio = trim($row_2['oficio']);
                        $pos = strpos($row["oficio"], $oficio);
                        if ($pos !== false) {
                            $url_oficio = $row_2['url_oficio'];
                            DB::statement("UPDATE `69` SET oficio='".$oficio."',url_oficio='".$url_oficio."' WHERE rfc='".$row["rfc"]."' AND tipo='".$row["tipo"]."'");
                            break;
                        }
                    }
                }

                /*Sentencia favorable*/
                if ($row["tipo"] == "Sentencia favorable") {
                    foreach ($Sentencia as $row_2) {
                        $oficio = trim($row_2['oficio']);
                        $pos = strpos($row["oficio"], $oficio);
                        if ($pos !== false) {
                            $url_oficio = $row_2['url_oficio'];
                            DB::statement("UPDATE `69` SET oficio='".$oficio."',url_oficio='".$url_oficio."' WHERE rfc='".$row["rfc"]."' AND tipo='".$row["tipo"]."'");
                            break;
                        }
                    }
                }
                $this->progressBar($index, ($total - 1));
            }
            echo PHP_EOL . "Registrar tabla para comparar" . PHP_EOL;
            DB::statement("INSERT INTO `69_Clone` SELECT * FROM `69`");
            //DB::statement("CREATE TABLE `69_Clone` AS SELECT * FROM `69`");
        }else{
            echo PHP_EOL . "Sin resultados en la BD..." . PHP_EOL;
        }
        echo "Proceso terminado..." . PHP_EOL;
        $email_data = array(
            'recipient' => ['miguel_caballero92@hotmail.com','cmiguel@advans.mx'],
            'subject' => 'Actualización de 69B'
        );
        $dump_sql = sys_get_temp_dir() . '/anexo69_'.date('Ymd').'.sql';
        shell_exec('mysqldump anexo69 69 > '. $dump_sql);

        $data = array($Nuevo,$Clone);
        Mail::send('layouts.anexo', ['antes' => $Clone, 'despues' => $Nuevo,'content' => 'Hubo actualización de anexo 69B' ], function ($message) use ($email_data, $dump_sql) {
            $message->from('cmiguel@advans.mx', 'Laravel')
                ->to($email_data['recipient'])
                ->subject($email_data['subject'])
                ->attach($dump_sql);
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
