<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 *CREATE TABLE `pracownik_umowy` (
`id_umowy` bigint(20) NOT NULL,
`fk_pracownik` bigint(20) NOT NULL,
`data_zakonczenia` date NOT NULL,
`data_rozpoczecia` date NOT NULL,
`zus_pracownik` decimal(13,2) DEFAULT NULL,
`zus_pracodawca` decimal(13,2) DEFAULT NULL,
`do_wyplaty` decimal(13,2) DEFAULT NULL,
`brutto` double(13,2) NOT NULL,
`umowa` varchar(100) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
 */
class Umowy_model extends CI_Model
{

    private $_limit = 100;

    const columns = array(
        'A' => "Z",
        'B' => "Pracownik",
        'C' => "Data rachunku",
        'D' => "Numer",
        'E' => "Wartość",
        'F' => "Do wypłaty",
        'G' => "ZUS od pracownika",
        'H' => "ZUS pracodawcy",
        'I' => "Umowa",
        'J' => "Data rozpoczęcia",
        'K' => "Data zakończenia",
        'L' => "FW");
    const validators = array(
        'J' => 'date',
        'K' => 'date',
        'E' => 'float',
        'F' => 'float',
        'G' => 'float',
        'I' => 'string',
    );
    const validators_errors = array(
        'float' => "Wartość nie jest liczbą",
        'string' => "Wartość nie jest poprawna",
        'date' => "Wartość nie jest datą"
    );
    protected $_required = array(
        'B', 'D', 'E', 'F', 'G', 'H', 'I', 'J'
    );
    private $_sheet = array();
    private $_sheet_pracownicy = array();
    private $_agregacja = array();
    protected $_invalid_rows = array();

    var $table = 'pracownik_umowy';
    var $column_order = array(
        null,
        'fk_pracownik',
        'data_zakonczenia',
        'data_rozpoczecia',
        'zus_pracownik',
        'zus_pracodawca',
        'do_wyplaty',
        'brutto',
        'umowa',
    ); //set column field database for datatable orderable
    var $column_search = array(); //set column field database for datatable searchable
    var $order = array('data_rozpoczecia' => 'asc'); // default order

    public function __construct()
    {
        parent::__construct();
    }

    public function read_data(array $dane)
    {
        if (count($dane) > $this->_limit) {
            throw new Exception('Limit wierszy to ' . $this->_limit);
        }
        $this->_sheet = $dane;
        return $this;
    }

    public function column_validation()
    {
        foreach ($this->_required as $r) {
            if (!isset($this->_sheet[1][$r]) || $this->_sheet[1][$r] != self::columns[$r] || !array_key_exists($r, $this->_sheet[1])
            ) {
                throw new Exception('Kolumna - ' . $r . ' - Wartość nagłówka nie pasuje do szablonu, powinno być ' . self::columns[$r]);
            }
        }

        return $this;
    }

    function validateDate($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function row_validation($k, $a, $v, $f)
    {

        switch ($v) {
            case "date":
                $cellval = $this->validateDate(PHPExcel_Style_NumberFormat::toFormattedString($f, PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD));
                break;
            case "float":
                $cellval = is_float($f);
                if ($f === "0" or empty($f) or !isset($f)) {
                    $cellval = 1;
                }
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

    public function get_sheet_data()
    {
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
                echo @$this->row_validation($k, $a, $v, $d[$k]);
            }
            if (!is_null($d["B"]) && !empty($d["B"])) {

                // $this->_sheet_pracownicy[$d["H"]]["numer"] = PHPExcel_Style_NumberFormat::toFormattedString($d["E"], PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);

                if ($this->get_worker_id($d["B"])) {
                    $this->_sheet_pracownicy[] = array(
                        "pracownik" => $d["B"],
                        "data_zakonczenia" => PHPExcel_Style_NumberFormat::toFormattedString($d["K"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                        "data_rozpoczecia" => PHPExcel_Style_NumberFormat::toFormattedString($d["J"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                        "zus_pracownik" => (!empty($d["G"])) ? $d["G"] : 0.00,
                        "zus_pracodawca" => (!empty($d["H"])) ? $d["H"] : 0.00,
                        "zus_lacznie" => bcadd((!empty($d["G"])) ? $d["G"] : 0.00, (!empty($d["H"])) ? $d["H"] : 0.00),
                        "do_wyplaty" => (!empty($d["F"])) ? $d["F"] : 0.00,
                        "brutto" => (!empty($d["E"])) ? $d["E"] : 0.00,
                        "id_prac" => $this->get_worker_id($d["B"]),
                        "umowa" => $d["I"]);
                    $zus_pracownik = bcadd($zus_pracownik, (!empty($d["G"])) ? $d["G"] : 0.00);
                    $zus_pracodawca = bcadd($zus_pracodawca, (!empty($d["H"])) ? $d["H"] : 0.00);
                    $zus_lacznie = bcadd($zus_lacznie, bcadd((!empty($d["G"])) ? $d["G"] : 0.00, (!empty($d["H"])) ? $d["H"] : 0.00));
                    $do_wyplaty = bcadd($do_wyplaty, (!empty($d["F"])) ? $d["F"] : 0.00);
                    $brutto = bcadd($brutto, (!empty($d["E"])) ? $d["E"] : 0.00);
                }
            }
        }
        $this->_agregacja = array(
            "zus_pracownik" => $zus_pracownik,
            "zus_pracodawca" => $zus_pracodawca,
            "zus_lacznie" => $zus_lacznie,
            "do_wyplaty" => $do_wyplaty,
            "brutto" => $brutto,
            "razem" => count($dane),
            "dodano" => count($this->_sheet_pracownicy),
        );
       $this->dodanie_wpisu();
        return $this;
    }

    public function display_result()
    {
        if (empty($this->_invalid_rows)) {
            return array(
                "wartosci" => $this->_sheet_pracownicy,
                "agregacja" => $this->_agregacja
            );
        }
    }

    public function display_errors()
    {
        foreach ($this->_invalid_rows as $k => $a) {
            foreach ($a as $key => $value) {
                throw new Exception('Pole ' . $key . '' . $k . ' ' . self::validators_errors[$value]);
            }
        }
        return $this;
    }

    public function get_worker_id($getAd)
    {

        $this->db->select('id_pracownika as id')
            ->from('pracownicy')
            ->like('CONCAT( imie,  \' \', nazwisko )', $getAd)
            ->or_like('CONCAT( nazwisko,  \' \', imie )', $getAd);


        $query = $this->db->get();


        $result = $query->result_array();
        if (isset($result[0]["id"])) {
            return $result[0]["id"];
        } else {
            //throw new Exception('Nie odnaleziono ' . $getAd . ' w bazie danych, proszę dodać pracownika a następnie ponownie wczytać plik');
        }
    }

    protected function sprawdz_duplikat($ms, $pr, $data, $u)
    {
        $this->db->select('id_umowy as id')
            ->from('pracownik_umowy')
            ->where('data_zakonczenia', $ms)
            ->where('data_rozpoczecia', $data)
            ->where('fk_pracownik', $pr)->where('umowa', $u);


        $query = $this->db->get();

        $result = $query->result_array();
        if (!empty($result)) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public function dodanie_wpisu()
    {
        try {
            $this->db->trans_begin();
            $cur_w = "";
            foreach ($this->_sheet_pracownicy as $i) {
                $cur_w = $i['pracownik'];
                $cur_z = $i['data_zakonczenia'];
                $cur_r = $i['data_rozpoczecia'];
                $do_w = $i['brutto'];
                $u = $i['umowa'];
                if ($this->sprawdz_duplikat($i["data_zakonczenia"], $i["id_prac"], $i["data_rozpoczecia"], $i["umowa"])) {

                    $post_data = array(
                        'fk_pracownik' => $i["id_prac"],
                        'brutto' => $i["brutto"],
                        'zus_pracownik' => $i["zus_pracownik"],
                        'zus_pracodawca' => $i["zus_pracodawca"],
                        'do_wyplaty' => $i["do_wyplaty"],
                        "data_zakonczenia" => $i["data_zakonczenia"],
                        "data_rozpoczecia" => $i["data_rozpoczecia"],
                        "umowa" => $i["umowa"]
                    );


                    $this->db->insert('pracownik_umowy', $post_data);
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
            throw new Exception('Duplikat wpisu - Pracownik ' . $cur_w . ' posiada już dodaną umowę ' . $u . ' , okres umowy ' . $cur_r . ' - ' . $cur_z . ' wartość umowy ' . $do_w. 'zł , usuń wiersz z pliku i spróbuj ponownie');
            $this->db->trans_rollback();
        }
    }

    public function Dodaj()
    {

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

    private function _get_datatables_query()
    {

        $this->db->select("*");
        $this->db->join('pracownicy', 'pracownik_umowy.fk_pracownik = pracownicy.id_pracownika', 'left');

        //add custom filter here s_narzecz s_kontrakt

        if ($this->input->post('s_pracownik')) {
            $this->db->where('`fk_pracownik`', $this->input->post('s_pracownik'));
        }

        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'].'-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('data_rozpoczecia <=', date('Y-m-01', strtotime($query_date)));
           // $this->db->where($q . ' <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }

        $this->db->from($this->table);


        if (isset($_POST['order'])) { // here order processing
            $this->db->order_by($this->column_order[$_POST['order']['0']['column']], $_POST['order']['0']['dir']);
        } else if (isset($this->order)) {
            $order = $this->order;
            $this->db->order_by(key($order), $order[key($order)]);
        }
    }

    public function get_datatables()
    {
        $this->_get_datatables_query();
        $skip = FALSE;
        if (empty($this->input->post('length'))) {
            $l = 10;
            $s = 0;
        } else {
            $l = $this->input->post('length');
            $s = $this->input->post('start');
        }
        if ($this->input->post('length') == -1) {
            $skip = TRUE;
        }

        if (!$skip) {
            $this->db->limit($l, $s);
        }


        $query = $this->db->get();
        //echo $this->db->last_query();
        return $query->result();
    }

    public function agregacja($get)
    {


        $zus_pracownik = 0;
        $lacznie_brutto = 0;
        $zus_pracodawca = 0;
        $do_wyplaty = 0;


        if (!empty($get)) {
            foreach ($get as $a) {
                $lacznie_brutto = bcadd($lacznie_brutto, $a['brutto'], 2);
                $zus_pracownik = bcadd($zus_pracownik, $a['zus_pracownik'], 2);
                $zus_pracodawca = bcadd($zus_pracodawca, $a['zus_pracodawca'], 2);
                $do_wyplaty = bcadd($do_wyplaty, $a['do_wyplaty'], 2);
            }
        }

        return
            array(
                'zus_pracownik' => $zus_pracownik,
                'brutto' => $lacznie_brutto,
                'zus_pracodawca' => $zus_pracodawca,
                'zus_lacznie' => bcadd($zus_pracodawca, $zus_pracownik),
                'do_wyplaty' => $do_wyplaty,
            );
    }

    public function count_filtered()
    {

        $this->_get_datatables_query();
        $query = $this->db->get();
        return array(
            'count' => $query->num_rows(),
            'agregacja' => $this->agregacja($query->result_array())
        );
    }

    public function count_all()
    {
        $this->db->from($this->table);
        return $this->db->count_all_results();
    }

}
