<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Przelewy_model extends CI_Model
{

    private $_limit = 500;

    const columns = array(
        'A' => "Data operacji",
        'B' => "Data waluty",
        'C' => "Typ transakcji",
        'D' => "Kwota",
        'E' => "Waluta",
        'F' => "Saldo po transakcji",
        'G' => "Opis transakcji",
        'I' => ""
    );

    const validators = array(
        'A' => 'date',
        'B' => 'date',
        'C' => 'string',
        'D' => 'float',
        'E' => 'string',
        'F' => 'float',
        'G' => 'string',
        'I' => 'string'
    );


    const validators_errors = array(
        'float' => "Wartość nie jest liczbą",
        'string' => "Wartość nie jest poprawna",
        'date' => "Wartość nie jest datą"
    );

    protected $_required = array(
        'A', 'B', 'C', 'D'
    );
    private $_sheet = array();
    private $_sheet_pracownicy = array();
    private $_agregacja = array();
    protected $_invalid_rows = array();

    public function __construct() {
        parent::__construct();
    }

    public function read_data(array $dane) {
        if (count($dane) > $this->_limit) {
            throw new Exception('Limit wierszy to ' . $this->_limit);
        }
        $this->_sheet = $dane;
        return $this;
    }

    public function column_validation() {
        foreach ($this->_required as $r) {
            if (!isset($this->_sheet[1][$r]) || $this->_sheet[1][$r] != self::columns[$r] || !array_key_exists($r, $this->_sheet[1])
            ) {
                throw new Exception('Kolumna - ' . $r . ' - Wartość nagłówka nie pasuje do szablonu, powinno być ' . self::columns[$r]);
            }
        }

        return $this;
    }

    function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function row_validation($k, $a, $v, $f) {

        switch ($v) {
            case "date":
                $cellval = $this->validateDate(PHPExcel_Style_NumberFormat::toFormattedString($f, PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD));
                break;
            case "float":
                $cellval = is_float($f);
                break;
            case "string":
                $cellval = is_string($f);
                break;
            default:
                break;
        }
        if (!$cellval) {
            $this->_invalid_rows[$a][$k] = $v;
        }
    }



    public function get_sheet_data() {
        $dane = $this->_sheet;
        unset($dane[1]); // remove first col


        $Kwota = 0;

        foreach ($dane as $a => $d) {
            foreach (self::validators as $k => $v) {
                echo $this->row_validation($k, $a, $v, $d[$k]);
            }
            if (!is_null($d["D"]) && !empty($d["D"])) {
                // $this->_sheet_pracownicy[$d["H"]]["numer"] = PHPExcel_Style_NumberFormat::toFormattedString($d["E"], PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);

                if(strlen($d['I']) == 0){
                    $this->_sheet_pracownicy[] = array(
                        "Data_waluty" => PHPExcel_Style_NumberFormat::toFormattedString($d["B"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                        "Data_operacji" => PHPExcel_Style_NumberFormat::toFormattedString($d["A"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                        "Kwota" => $d["D"],
                        "Typ_transakcji" => str_replace("Tytuł:  ","",$d["G"])
                    );

                }else{
                    $this->_sheet_pracownicy[] = array(
                        "Data_waluty" => PHPExcel_Style_NumberFormat::toFormattedString($d["B"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                        "Data_operacji" => PHPExcel_Style_NumberFormat::toFormattedString($d["A"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                        "Kwota" => $d["D"],
                        "Typ_transakcji" => str_replace("Tytuł:  ","",$d["I"])
                    );

                }

                $Kwota = bcadd($Kwota, $d["D"]);
            }
        }
        $this->_agregacja = array(
            "Kwota_total" => $Kwota,
        );
        $this->dodanie_wpisu();
        return $this;
    }

    public function display_result() {
        if (empty($this->_invalid_rows)) {
            return array(
                "wartosci" => $this->_sheet_pracownicy,
                "agregacja" => $this->_agregacja
            );
        }
    }

    public function display_errors() {
        foreach ($this->_invalid_rows as $k => $a) {
            foreach ($a as $key => $value) {
                if($key === "I"){

                }else{
                    throw new Exception('Pole ' . $key . '' . $k . ' ' . self::validators_errors[$value]);
                }

            }
        }
        return $this;
    }


    protected function sprawdz_duplikat($ms,$dw, $pr, $data) {
        $this->db->select('id')
            ->from('przychody_przelewy')
            ->where('data_operacji', $ms)
            ->where('data_waluty', $dw)
            ->where('typ', $pr)
            ->where('kwota', $data);


        $query = $this->db->get();

        $result = $query->result_array();
        if (!empty($result)) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public function dodanie_wpisu() {
        try {
            $this->db->trans_begin();
            $cur_w = "";
            foreach ($this->_sheet_pracownicy as $i) {
                $cur_w = $i['Data_waluty'];
                $cur_m = $i['Data_operacji'];
                $cur_p = $i['Typ_transakcji'];
                $do_w = $i['Kwota'];
                if ($this->sprawdz_duplikat($cur_m,$cur_w, $cur_p, $do_w)) {

                    $post_data = array(
                        'data_waluty' => $i["Data_waluty"],
                        'data_operacji' => $i["Data_operacji"],
                        'typ' => $i["Typ_transakcji"],
                        'kwota' => $i["Kwota"],
                    );
                    $this->db->insert('przychody_przelewy', $post_data);
                } else {
                    $this->db->trans_rollback();
                    throw new Exception();
                }
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $e) {
            throw new Exception('Powielenie wpisu - Data waluty : '.$cur_w.' Data operacji : '.$cur_m.' '.$cur_p.' Kwota : '.$do_w.'proszę usunąć wpis z pliku i spróbować ponownie.');
            $this->db->trans_rollback();
        }
    }

    public function Dodaj() {

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $status = FALSE;
        $message = "";


        if (!empty($_FILES['inputSkan']['name'])) {
            $config['upload_path'] = './files/import';
            $config['allowed_types'] = 'xlsx';
            $config['max_size'] = 5000000; // 5 mb
            $config['encrypt_name'] = TRUE;
            if (!is_dir($config['upload_path'])) {
                mkdir($config['upload_path'], 0777, TRUE);
            }
            $this->load->library('upload', $config);

            if (!$this->upload->do_upload("inputSkan")) {
                $status = 'error';
                $msg = $this->upload->display_errors('', '');
                echo json_encode(array('result' => 'error', 'msg' => $msg));
            } else {
                $data = $this->upload->data();
                $message = "files/import/" . $data['file_name'];
                $status = 1;
            }
        }
        return $message;

    }

}
