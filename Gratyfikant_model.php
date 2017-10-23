<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 *
 */
class Gratyfikant_model extends CI_Model {

    private $_limit = 100;

    const columns = array(
        'A' => "Z",
        'B' => "KS",
        'C' => "G",
        'D' => "S",
        'E' => "Numer",
        'F' => "Miesiąc", // required
        'G' => "Data wypłaty", // required
        'H' => "Pracownik", // required
        'I' => "Brutto duże", // required
        'J' => "ZUS pracownik", // required
        'K' => "ZUS pracodawca", // required
        'L' => "Do wypłaty", // required 
        'M' => "Obciążenie", // required
        'N' => "FW");
    const validators = array(
        'F' => 'date',
        'G' => 'date',
        'H' => 'string',
        'I' => 'float',
        'J' => 'float',
        'K' => 'float',
        'L' => 'float',
        'M' => 'float',
    );
    const validators_errors = array(
        'float' => "Wartość nie jest liczbą",
        'string' => "Wartość nie jest poprawna",
        'date' => "Wartość nie jest datą"
    );

    protected $_required = array(
        'H', 'I', 'J', 'K', 'L', 'M'
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

        $zus_pracownik = 0;
        $zus_pracodawca = 0;
        $zus_lacznie = 0;
        $do_wyplaty = 0;
        $obciazenie = 0;
        $brutto = 0;

        foreach ($dane as $a => $d) {
            foreach (self::validators as $k => $v) {
                echo $this->row_validation($k, $a, $v, $d[$k]);
            }
            if (!is_null($d["H"]) && !empty($d["H"])) {
                // $this->_sheet_pracownicy[$d["H"]]["numer"] = PHPExcel_Style_NumberFormat::toFormattedString($d["E"], PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
                $this->_sheet_pracownicy[] = array(
                    "pracownik" => $d["H"],
                    "miesiac" => PHPExcel_Style_NumberFormat::toFormattedString($d["F"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                    "data_wyplaty" => PHPExcel_Style_NumberFormat::toFormattedString($d["G"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                    "zus_pracownik" => $d["J"],
                    "zus_pracodawca" => $d["K"],
                    "zus_lacznie" => bcadd($d["K"], $d["J"]),
                    "do_wyplaty" => $d["L"],
                    "obciazenie" => $d["M"],
                    "brutto" => $d["I"],
                    "id_prac" => $this->get_worker_id($d["H"]));

                $zus_pracownik = bcadd($zus_pracownik, $d["J"]);
                $zus_pracodawca = bcadd($zus_pracodawca, $d["K"]);
                $zus_lacznie = bcadd($zus_lacznie, bcadd($d["K"], $d["J"]));
                $do_wyplaty = bcadd($do_wyplaty, $d["L"]);
                $obciazenie = bcadd($obciazenie, $d["M"]);
                $brutto = bcadd($brutto, $d["I"]);
            }
        }
        $this->_agregacja = array(
            "zus_pracownik" => $zus_pracownik,
            "zus_pracodawca" => $zus_pracodawca,
            "zus_lacznie" => $zus_lacznie,
            "do_wyplaty" => $do_wyplaty,
            "obciazenie" => $obciazenie,
            "brutto" => $brutto
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
                throw new Exception('Pole ' . $key . '' . $k . ' ' . self::validators_errors[$value]);
            }
        }
        return $this;
    }

    public function get_worker_id($getAd) {

        $this->db->select('id_pracownika as id')
                ->from('pracownicy')
                ->like('CONCAT( imie,  \' \', nazwisko )', $getAd)
                ->or_like('CONCAT( nazwisko,  \' \', imie )', $getAd);


        $query = $this->db->get();



        $result = $query->result_array();
        if (isset($result[0]["id"])) {
            return $result[0]["id"];
        } else {
            throw new Exception('Nie odnaleziono ' . $getAd . ' w bazie danych, proszę dodać pracownika a następnie ponownie wczytać plik');
        }
    }

    protected function sprawdz_duplikat($ms, $pr, $data) {
        $this->db->select('id_placy as id')
                ->from('pracownik_place')
                ->where('miesiac', $ms)
                ->where('fk_prac', $pr)
                ->where('data_wyplaty', $data);


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
                $cur_w = $i['pracownik'];
                $cur_m = $i['miesiac'];
                $cur_p = $i['data_wyplaty'];
                $do_w = $i['do_wyplaty'];
                if ($this->sprawdz_duplikat($i["miesiac"], $i["id_prac"], $i["data_wyplaty"])) {

                    $post_data = array(
                        'fk_prac' => $i["id_prac"],
                        'miesiac' => $i["miesiac"],
                        'data_wyplaty' => $i["data_wyplaty"],
                        'brutto' => $i["brutto"],
                        'zus_pracownik' => $i["zus_pracownik"],
                        'zus_pracodawca' => $i["zus_pracodawca"],
                        'do_wyplaty' => $i["do_wyplaty"],
                        'obciazenie' => $i["obciazenie"],
                    );
                    $this->db->insert('pracownik_place', $post_data);
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
            throw new Exception('Powielenie wpisu - ' . $cur_w . ' posiada już wpis o wypłacie za miesiąc ' . $cur_m . ' z dniem wypłaty ' . $cur_p . ' w wysokości ' . $do_w . ' proszę usunąć wpis z pliku i spróbować ponownie.');
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

        //return $this->output
        //                ->set_content_type('application/json')
        //               ->set_status_header(200)
        //                ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
    }

}
