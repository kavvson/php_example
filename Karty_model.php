<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * CREATE TABLE `pracownik_bank` (
 * `id_transakcji` bigint(20) NOT NULL,
 * `fk_pracownik` bigint(20) NOT NULL,
 * `data_operacji` date NOT NULL,
 * `typ_transakcji` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
 * `kwota` decimal(13,2) NOT NULL
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
 *
 * ALTER TABLE `pracownik_bank`
 * ADD PRIMARY KEY (`id_transakcji`),
 * ADD KEY `fk_pracownik` (`fk_pracownik`);
 *
 * ALTER TABLE `pracownik_bank`
 * MODIFY `id_transakcji` bigint(20) NOT NULL AUTO_INCREMENT;COMMIT;
 */
class Karty_model extends CI_Model
{

    private $_limit = 100;

    private $_pracownik = NULL;

    const columns = array(
        'A' => "Data operacji",
        'B' => "Data waluty",
        'C' => "Typ transakcji",
        'D' => "Kwota",
        'E' => "Waluta",
        'F' => "Saldo po transakcji",
        'G' => "Opis transakcji",
        'M' => "",
    );
    const validators = array(
        'A' => 'date',
        'B' => 'date',
        'D' => 'float',
        'C' => 'string',
        'M' => 'identyfikator'
    );
    const validators_errors = array(
        'float' => "Wartość nie jest liczbą",
        'string' => "Wartość nie jest poprawna",
        'date' => "Wartość nie jest datą",
        'identyfikator' => "Nie prawidłowy identyfikator transakcji",
    );
    protected $_required = array(
        'A', 'B', 'C', 'D'
    );
    const metoda_platnosci = [
        "1" => "Gotówka",
        "2" => "Przelew",
        "3" => "Karta",
    ];
    const karta_metoda_platnosci = [
        "Płatność kartą" => 3,
        "Wypłata z bankomatu" => 1
    ];
    const akcetowane_metody_platnosci = [
        "Płatność kartą", "Wypłata z bankomatu"
    ];

    private $_sheet = array();
    private $_sheet_pracownicy = array();

    private $_suma_bankomat = array();

    private $_agregacja = array();
    protected $_invalid_rows = array();

    var $table = 'pracownik_bank';
    var $column_order = array(
        null,
        'fk_pracownik',
        'typ_transakcji',
        'data_operacji',
        'data_waluty',
        'kwota',
        'rozliczona_kwota',
        'dokument',
    );

    var $column_search = array(); //set column field database for datatable searchable
    var $order = array('data_operacji' => 'asc'); // default order

    public function __construct()
    {
        parent::__construct();
    }

    public function getPracownik()
    {
        return $this->_pracownik;
    }

    public function setPracownik($pracownik)
    {
        $prac = $this->get_worker_id($pracownik);
        if (empty($prac)) {
            throw new Exception('Nie odnaleziono pracownika, upewnij się, że nazwa pliku to imię i nazwisko pracownika zwróc uwagę na wielkość liter. Jeżeli nazwa jest poprawna to danego pracownika ' . $pracownik . ' nie ma w bazie danych');
        }
        $this->_pracownik = $prac;
        return $this;
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
                $cellval = is_float((float)$f);
                break;
            case "string":
                $cellval = is_string($f);
                break;
            case "identyfikator":
                preg_match("/[1-9][0-9]*/", $f, $output_array);
                if (empty($output_array)) {
                    $cellval = false;
                } else {
                    $cellval = TRUE;
                }

                break;
            default:
                break;
        }
        if (!$cellval) {
            $this->_invalid_rows[$a][$k] = $v;
        }
    }

    public function cat_agregat($bankname, $array, $dt = FALSE)
    {
        $result = -1;
        if ($dt) {
            $f = "typ_transakcji";

        } else {
            $f = "Typ transakcji";
        }
        for ($i = 0; $i < sizeof($array); $i++) {
            if ($array[$i][$f] == $bankname) {
                $result = $i;
                break;
            }
        }
        return $result;
    }

    public function get_sheet_data()
    {
        $dane = $this->_sheet;
        unset($dane[1]); // remove first col
        $wyplaty = 0;
        $first_d = "";
        $count = 0;
        foreach ($dane as $a => $d) {
            foreach (self::validators as $k => $v) {
                echo @$this->row_validation($k, $a, $v, $d[$k]);
            }
            if (!is_null($d["B"]) && !empty($d["B"])) {

                if ($d["C"] === "Wypłata z bankomatu") {

                    $wyplaty = bcadd($wyplaty, str_replace(",", ".", $d["D"]), 2);
                    $first_d = PHPExcel_Style_NumberFormat::toFormattedString($d["B"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD);
                    $count++;
                } else {
                    $this->_sheet_pracownicy[] = array(
                        self::columns["A"] => PHPExcel_Style_NumberFormat::toFormattedString($d["A"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                        self::columns["B"] => PHPExcel_Style_NumberFormat::toFormattedString($d["B"], PHPExcel_Style_NumberFormat::FORMAT_DATE_YMD),
                        self::columns["C"] => $d["C"],
                        self::columns["D"] => str_replace(",", ".", $d["D"]),
                        "id" => $d["M"]
                    );
                }


            }
        }

        $this->_sheet_pracownicy[] = array(
            self::columns["A"] => $first_d,
            self::columns["B"] => $first_d,
            self::columns["C"] => "Wypłata z bankomatu",
            self::columns["D"] => $wyplaty,
            "id" => $d["M"],
            "suma_c" => $count
        );

        $amount = array();
        foreach ($this->_sheet_pracownicy as $bank) {
            $index = $this->cat_agregat($bank['Typ transakcji'], $amount);
            if ($index < 0) {
                $amount[] = $bank;
            } else {
                $amount[$index]['Kwota'] += $bank['Kwota'];
            }
        }


        $this->_agregacja = $amount;

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
            throw new Exception('Nie odnaleziono ' . $getAd . ' w bazie danych, proszę dodać pracownika a następnie ponownie wczytać plik');
        }
    }

    public function custom_decimal($decimal)
    {
        $decimal = str_replace("Zł ", "", $decimal);
        $decimal = str_replace("dm3 ", "", $decimal);
        $decimal = str_replace(",", "", $decimal);


        if (preg_match('/^[0-9]+\.[0-9]{2}$/', $decimal)) {
            return $decimal;
        } else {
            return FALSE;
        }
    }

    public function dodaj_zaliczke($id)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = 0;
        $pracownik = $id;
        $kwota = $this->input->post("cf_zaliczka_kwota");
        $opis = $this->input->post("cf_zaliczka_opis");
        $data = $this->input->post("cf_zaliczka_data");

        $this->load->helper(array('form', 'url'));


        $this->load->library('form_validation');


        $this->form_validation->set_rules('cf_zaliczka_opis', 'rejon', 'required|max_length[250]|min_length[3]', array(
            'required' => 'Musisz podać opis zaliczki.',
            'max_length' => "Opis może mieć maksymalnie 250 znaków",
            'min_length' => "Opis musi się składać z conajmniej 3 znaków"
        ));

        $brutto = $this->custom_decimal($this->input->post('cf_zaliczka_kwota'));

        if (!$brutto || strlen($brutto) < 1 || $brutto === "0.00") {
            $message = "Kwota nie jest liczbą";
        }

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $data)) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }


        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            try {
                $this->db->trans_begin();
                $post_data = array(
                    'fk_pracownik' => $pracownik,
                    'data_operacji' => $data,
                    'data_waluty' => $data,
                    'typ_transakcji' => "Zaliczka",
                    'kwota' => $brutto,
                    'rozliczona_kwota' => 0,
                    'nr_transakcji' => "wew" . time(),
                    'opis' => $opis
                );

                $this->db->insert('pracownik_bank', $post_data);

                if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                    $status = 1;
                    $message = "Dodano";
                }
            } catch (Exception $e) {
                throw new Exception('');
                $this->db->trans_rollback();
            }
        }


        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
    }

    protected function sprawdz_duplikat($data_operacji, $Ttransakcji, $kwota, $pracownik, $trans)
    {
        $this->db->select('*')
            ->from('pracownik_bank')
            ->where('data_waluty', $data_operacji)
            ->where('typ_transakcji', $Ttransakcji)
            ->where('kwota', $kwota)->where('fk_pracownik', $pracownik)
            ->where('nr_transakcji', $trans);


        $query = $this->db->get();

        $result = $query->result_array();
        if (!empty($result)) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    function this_answer_solution(array $input_array)
    {
        return count($input_array) === count(array_flip($input_array));
    }

    public function rozlicz_karte()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $message = "";
        $dataroot = $this->input->post("data[Transakcja]");
        if (empty($dataroot)) {
            $message = "Wybierz conajmniej jeden wpis";
        }
        try {
            $unique = array();
            foreach ($dataroot as $k => $v) {
                // klucz transakcji
                if ($k != "Pracownik") {

                    foreach ($v["Wydatek"] as $w) {
                        $unique[] = $w;
                    }
                }
            }

            if (!$this->this_answer_solution($unique)) {
                $message = "Duplikat wydatku";
            }

            $this->db->trans_begin();
            if (strlen($message) === 0) {


                foreach ($dataroot as $k => $v) {
                    // klucz transakcji
                    if ($k != "Pracownik") {

                        foreach ($v["Wydatek"] as $w) {

                            $array = array(
                                'fk_transakcji' => $k,
                                'fk_wydatku' => $w
                            );

                            $this->db->insert('wydatki_bank', $array);


                            $this->db->select("kwota_brutto");
                            $this->db->where('id_wydatku', $w);
                            $this->db->from("wydatki");
                            $query = $this->db->get();
                            $tablica_wydatkow = $query->result_array();


                            $this->db->where('id_transakcji', $k);
                            $this->db->set('rozliczona_kwota', '`rozliczona_kwota`+ ' . $tablica_wydatkow[0]["kwota_brutto"], FALSE);
                            $this->db->update('pracownik_bank');
                        }

                    }

                }
            }

            if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
                $message = "Zmodyfikowano";
                $status = 1;
            }
        } catch (Exception $e) {

        }
        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
    }

    public function dodanie_wpisu()
    {
        die("fukcja wylaczona");
        try {
            $this->db->trans_begin();
            foreach ($this->_sheet_pracownicy as $i) {
                $trans = $i["Typ transakcji"];
                $do = $i["Data operacji"];
                $kwota = $i["Kwota"];
                $id = $i["id"];
                if ($trans != "Wypłata z bankomatu") {

                    preg_match("/[1-9][0-9]*/", $i["id"], $output_array);
                    if ($this->sprawdz_duplikat($i["Data waluty"], $i["Typ transakcji"], $i["Kwota"], $this->getPracownik(), $output_array[0])) {

                        $post_data = array(
                            'fk_pracownik' => $this->getPracownik(),
                            'data_operacji' => $i["Data operacji"],
                            'data_waluty' => $i["Data waluty"],
                            'typ_transakcji' => $i["Typ transakcji"],
                            'kwota' => $i["Kwota"],
                            'nr_transakcji' => $output_array[0]
                        );

                        $this->db->insert('pracownik_bank', $post_data);
                        $iID = $this->db->insert_id();
                        if (in_array($i["Typ transakcji"], self::akcetowane_metody_platnosci)) {
                            // dorzucic date
                            $found = $this->test_b(abs($i["Kwota"]), self::karta_metoda_platnosci[$i["Typ transakcji"]], $this->getPracownik(), $i["Data waluty"]);
                            if (!empty($found)) {

                                $bank = array(
                                    'fk_transakcji' => $iID,
                                    'fk_wydatku' => $found->id_wydatku,
                                );
                                $this->db->insert('wydatki_bank', $bank);

                                $u = array(
                                    'rozliczona_kwota' => abs($i["Kwota"]),
                                );
                                $this->db->where('id_transakcji', $iID);
                                $this->db->update('pracownik_bank', $u);

                            }
                        }
                    } else {
                        $this->db->trans_rollback();
                        throw new Exception();
                    }
                } else {
                    // wyplata z bankomatu
                    preg_match("/[1-9][0-9]*/", $i["id"], $output_array);
                    if ($this->sprawdz_duplikat($i["Data waluty"], $i["Typ transakcji"], $i["Kwota"], $this->getPracownik(), "sumaryczna")) {
                        //sprawdz dup

                        $post_data = array(
                            'fk_pracownik' => $this->getPracownik(),
                            'data_operacji' => $i["Data operacji"],
                            'data_waluty' => $i["Data waluty"],
                            'typ_transakcji' => $i["Typ transakcji"],
                            'kwota' => $i["Kwota"],
                            'nr_transakcji' => "sumaryczna"
                        );

                        $this->db->insert('pracownik_bank', $post_data);
                    } else {
                        $this->db->trans_rollback();
                        throw new Exception();
                    }
                }

            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();

            }
        } catch (Exception $e) {

            throw new Exception('Dany pracownik posiada już wpis : ( ' . $id . ' ) Data transkacji : ' . $do . ' Typ transakcji : ' . $trans . ' Kwota : ' . $kwota . ' usuń transkację z pliku i spróbuj ponownie');
            $this->db->trans_rollback();
        }

    }


    public function Dodaj()
    {
        die("fukcja wylaczona");
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";

        if (!empty($_FILES['inputSkan']['name'])) {
            $config['upload_path'] = './files/import';
            $config['allowed_types'] = 'xls|XLS';
            $config['max_size'] = 5000000; // 5 mb
            $config['encrypt_name'] = TRUE;
            if (!is_dir($config['upload_path'])) {
                mkdir($config['upload_path'], 0777, TRUE);
            }
            $this->load->library('upload', $config);

            if (!$this->upload->do_upload("inputSkan")) {

                $msg = $this->upload->display_errors('', '');
                throw new Exception($msg);
            } else {
                $data = $this->upload->data();
                $message = "files/import/" . $data['file_name'];
                $status = 1;
            }

        }


        return array("msg" => $message, "org" => $data["orig_name"]);
    }

    function rangeMonth($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('Y-m-d', strtotime('first day of this month', $dt));
        $res['end'] = date('Y-m-d', strtotime('last day of this month', $dt));
        return $res;
    }

    function rangeWeek($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
        $res['end'] = date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
        return $res;
    }

    private function _get_datatables_query()
    {

        $this->db->select("pracownicy.*,wydatki_bank.*,pracownik_bank.*,wydatki.dokument, wydatki.id_wydatku as wid,group_concat(`wydatki`.`dokument`) as ExpenseNumber,group_concat(`wydatki`.`id_wydatku`) as ExpenseID");
        $this->db->join('pracownicy', 'pracownik_bank.fk_pracownik = pracownicy.id_pracownika', 'left');
        $this->db->join('wydatki_bank', 'pracownik_bank.id_transakcji = wydatki_bank.fk_transakcji', 'left');

        $this->db->join('wydatki', 'wydatki_bank.fk_wydatku = wydatki.id_wydatku', 'left');

        $this->db->group_by('`pracownik_bank`.id_transakcji');

        if ($this->input->post('s_pracownik')) {
            $this->column_order = array(
                null,
                'typ_transakcji',
                'data_operacji',
                'data_waluty',
                'kwota',
                'rozliczona_kwota',
                'dokument',
            );
            $this->db->where('`fk_pracownik`', $this->input->post('s_pracownik'));
        }
        $this->db->where('`typ_transakcji`','Zaliczka');

        $this->zakres("default");


        $this->db->from($this->table);


        if (isset($_POST['order'])) { // here order processing
            $this->db->order_by($this->column_order[$_POST['order']['0']['column']], $_POST['order']['0']['dir']);
        } else if (isset($this->order)) {
            $order = $this->order;
            $this->db->order_by(key($order), $order[key($order)]);
        }
    }

    public function pobierz_pozostale($pracownik)
    {
        $message = "";
        $status = 0;
        $uzupelnij = array();
        // pobierz kwote gotówki
        $this->db->select("abs(kwota) as kwota,id_transakcji");
        $this->zakres("default");
        $this->db->where('fk_pracownik', $pracownik);
        $this->db->where('typ_transakcji', "Wypłata z bankomatu");
        $this->db->from("pracownik_bank");
        $q = $this->db->get();
        $kwota = $q->row_array();

        if (empty($kwota['kwota'])) {
            $message = "Na dany miesiąc nie ma żadnych Wypłata z bankomatu";
        }


        if (strlen($message) == 0) {
            // Pobierz niepodpiete
            $this->db->select("wydatki.id_wydatku,wydatki.kwota_brutto,dokument");
            $this->db->join('wydatki', 'wydatki_bank.fk_wydatku = wydatki.id_wydatku', 'right');

            $this->zakres("w");
            $this->db->where('id_powiazania IS NULL');
            $this->db->where('wydatki.id_kupujacy', $pracownik);
            $this->db->where('wydatki.metoda_platnosci', 1);

            $this->db->from("wydatki_bank");
            $query = $this->db->get();
            $tablica_wydatkow = $query->result_array();


            $ilosc = count($tablica_wydatkow);
            if ($ilosc == 0) {
                $message = "Brak wydatków do podpięcia";
            } else {
                $suma = 0;
                foreach ($tablica_wydatkow as $w) {

                    $suma += $w['kwota_brutto'];
                    if($suma <= $kwota['kwota']){
                        $uzupelnij[] = array(
                            "wydatek" => $w['id_wydatku'],
                            "kwota" => $w['kwota_brutto']
                        );

                        $array = array(
                            'fk_transakcji' => $kwota['id_transakcji'],
                            'fk_wydatku' => $w['id_wydatku']
                        );

                        $this->db->insert('wydatki_bank', $array);


                        $this->db->where('id_transakcji', $kwota['id_transakcji']);
                        $this->db->set('rozliczona_kwota', '`rozliczona_kwota`+ ' . $w['kwota_brutto'], FALSE);
                        $this->db->update('pracownik_bank');
                    }
                }
                $suma_do_uzupelnienia = array_sum( array_map(
                    function($element){
                        return $element['kwota'];
                    },
                    $uzupelnij));


                $message = "Odnalezione wpisy : " . $ilosc . " na sumę " . $suma . " zł 
                            <br> Suma Wypłat z bankomatu to " . $kwota['kwota']."
                            <br><hr>Podpięto automatycznie wpisów : ".count($uzupelnij). " w kwocie ".$suma_do_uzupelnienia." zł
                            <br>Brakująca kwota to ".bcsub($kwota['kwota'],$suma_do_uzupelnienia,2). " zł";
                $status = 1;
            }
        }


        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("response" => array("status" => $status, "message" => $message))));
    }


    public function zakres($pola)
    {


        if ($pola === "default") {
            $q = "pracownik_bank.data_waluty";
        } else {
            $q = "wydatki.data_zakupu";
        }
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where($q . ' >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where($q . ' <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
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

        return $query->result();
    }

    public function agregacja($get)
    {
        $amount = array();
        if (!empty($get)) {

            foreach ($get as $bank) {
                $index = $this->cat_agregat($bank['typ_transakcji'], $amount, TRUE);
                if ($index < 0) {
                    $amount[] = $bank;
                } else {
                    $amount[$index]['kwota'] += $bank['kwota'];
                }
            }
            return $amount;
        }
    }

    public function count_filtered()
    {

        $this->_get_datatables_query();
        $query = $this->db->get();

        //echo $this->db->last_query();
        return array(
            'count' => $query->num_rows(),
            'agregacja' => $this->agregacja($query->result_array()),
            'statystyka' => $this->Wydatki_do_karty($this->input->post("s_pracownik"))
        );
    }

    public function count_all()
    {
        $this->db->from($this->table);
        return $this->db->count_all_results();
    }


    /* LEGACY */

    public function test_b($kwota, $metoda, $pracownik, $data)
    {
        $this->db->select("wydatki.dokument,wydatki.kwota_brutto,wydatki.id_wydatku");
        $this->db->join('wydatki', 'platnosci.fk_wydatek = wydatki.id_wydatku');

        $query_date = date("y", strtotime($data)) . '-' . date("m", strtotime($data)) . '-01';

        $this->db->group_start();
        $this->db->where('wydatki.data_zakupu >=', date('Y-m-01', strtotime($query_date)));
        $this->db->where('wydatki.data_zakupu <=', date('Y-m-t', strtotime($query_date)));
        $this->db->group_end();

        $this->db->group_start();
        $this->db->where('wydatki.kwota_brutto >=', $kwota);
        $this->db->where('wydatki.kwota_brutto <=', bcadd($kwota, 0.01, 2));
        $this->db->group_end();
        $this->db->where('wydatki.metoda_platnosci', $metoda);
        $this->db->where('wydatki.id_kupujacy', $pracownik);
        $this->db->from("platnosci");
        $query = $this->db->get();

        $resu = $query->row();

        return $resu;
    }

    public function Wydatki_do_karty($id = NULL)
    {

        // Obliczanie sumy z wyciagow
        $this->db->select("sum(kwota_brutto) as kwota");

        $this->db->group_start();
        $this->db->where('metoda_platnosci', '3');
        $this->db->or_where('metoda_platnosci', '1');
        $this->db->group_end();
        // porownanie przy dodawaniu ?? obadac
        $this->zakres("wydatek");
        if ($id != NULL) {
            $this->db->where('wydatki.id_kupujacy', $id);
        }

        $this->db->from("wydatki");
        $query = $this->db->get();


        $tablica_wydatkow = $query->result_array();


        // Obliczanie sumy z karty
        $this->db->select("sum(abs(kwota)) as kwota");

        $this->db->group_start();
        $this->db->where('typ_transakcji', 'Wypłata z bankomatu');
        $this->db->or_where('typ_transakcji', 'Płatność kartą');
        $this->db->or_where('typ_transakcji', 'Zaliczka');
        $this->db->group_end();
        // porownanie przy dodawaniu ?? obadac
        $this->zakres("default");
        if ($id != NULL) {
            $this->db->where('pracownik_bank.fk_pracownik', $id);
        }

        $this->db->from("pracownik_bank");
        $query = $this->db->get();

        $tablica_kart = $query->result_array();

        //echo $this->db->last_query();

        // Rozliczono w programie + pozostalo do rozliczenia
        $this->db->select("pracownik_bank.id_transakcji,
abs(pracownik_bank.rozliczona_kwota) as rozliczona,
abs(pracownik_bank.kwota) as kwota,
IF(SUM(wydatki.kwota_brutto) IS NULL,0,SUM(wydatki.kwota_brutto)) as wydatek,
IF(SUM(wydatki.kwota_brutto) IS NULL,abs(pracownik_bank.kwota),IF(abs(pracownik_bank.kwota) - SUM(wydatki.kwota_brutto) < 0,0, abs(pracownik_bank.kwota) - SUM(wydatki.kwota_brutto))) as pozostalo");
        $this->db->join('wydatki', 'wydatki_bank.fk_wydatku = wydatki.id_wydatku', 'RIGHT');
        $this->db->join('pracownik_bank', 'wydatki_bank.fk_transakcji = pracownik_bank.id_transakcji', 'RIGHT');
        $this->db->group_start();
        $this->db->where('typ_transakcji', 'Wypłata z bankomatu');
        $this->db->or_where('typ_transakcji', 'Płatność kartą');
        $this->db->or_where('typ_transakcji', 'Zaliczka');
        $this->db->group_end();

        $this->zakres("default");
        if ($id != NULL) {
            $this->db->where('pracownik_bank.fk_pracownik', $id);
        }
        $this->db->from("wydatki_bank");
        $this->db->group_by("pracownik_bank.id_transakcji,pracownik_bank.rozliczona_kwota");
        $query = $this->db->get();

        $roznice = $query->result_array();


        $lacznie_bank = 0;
        $lacznie_wydatkow = 0;
        $ze_swojej = 0;
        $wys = 0;
        $pozostala_kwota = 0;
        $rozliczone_wydatki = 0;

        if (!empty($tablica_wydatkow[0]["kwota"])) {
            $lacznie_wydatkow = $tablica_wydatkow[0]["kwota"];
        }
        if ($tablica_kart[0]) {
            $lacznie_bank = $tablica_kart[0]["kwota"];
        }


        if (!empty($roznice[0])) {
            foreach ($roznice as $calc) {

                $rozliczone_wydatki += $calc["wydatek"];
                $pozostala_kwota += $calc["pozostalo"];

                if ($calc["pozostalo"] === "0.00") {
                    $wys++;
                }
            }

            $ze_swojej = "TBD";
        }

        return [
            "wyniki" => 0,
            "ze_swojej" => $ze_swojej,
            "nie_roz" => abs($pozostala_kwota),
            "znaleziono" => $wys,
            "suma" => $rozliczone_wydatki,
            "suma_wydatkow" => $lacznie_wydatkow,
            "suma_karta" => abs($lacznie_bank)
        ];
    }

    /*
     * POST::: "parnet":"41","id":"86","val":"34622017"
     */
    public function OdepnijZKarty()
    {
        die("fukcja wylaczona");
        $message = "";
        try {
            $this->db->trans_begin();
            $parent = $this->input->post("parent"); // transakcja id
            $id = $this->input->post("id"); // id wydatku
            $val = $this->input->post("val"); // nazwa wydatku

            if (empty($parent) || empty($id) || !is_numeric($parent) || !is_numeric($id)) {
                $message = "Wystąpił błąd";
            }

            /* Pobierz dane wydatku */
            $this->db->select("kwota_brutto as kwota");
            $this->db->where('id_wydatku', $id);
            $this->db->where('dokument', $val);
            $this->db->from("wydatki");
            $query = $this->db->get();


            $tablica_wydatkow = $query->result_array();


            if (empty($tablica_wydatkow[0]["kwota"])) {
                $message = "Nie udało się pobrać kwoty podatku";
            }

            /* Update kwote */
            $this->db->set('rozliczona_kwota', 'rozliczona_kwota-' . $tablica_wydatkow[0]["kwota"], FALSE);
            $this->db->where('id_transakcji', $parent);
            $this->db->update('pracownik_bank'); // gives UPDATE mytable SET field = field+1 WHERE id = 2


            /* Delete powiazanie */

            $this->db->where('fk_transakcji', $parent);
            $this->db->where('fk_wydatku', $id);
            $this->db->limit(1);
            $this->db->delete('wydatki_bank');


            if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                $this->db->trans_rollback();
                $status = 0;
            } else {
                $this->db->trans_commit();
                $message = "Zmodyfikowano";
                $status = 1;
            }
        } catch (Exception $e) {
            throw new Exception("");
            $this->db->trans_rollback();
        }


        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
    }


    /* OLD
    public function Wydatki_do_karty($id)
    {
        $this->db->select("wydatki.*,platnosci.*,sum(wydatki.kwota_brutto) as swy");
        $this->db->join('wydatki', 'platnosci.fk_wydatek = wydatki.id_wydatku');
        $this->db->group_start();
        $this->db->where('wydatki.data_zakupu >=', date('Y-m-d', strtotime('first day of last month')));
        $this->db->where('wydatki.data_zakupu <=', "2017-09-05");
        //$this->db->where('wydatki.data_zakupu <=', date('Y-m-d', strtotime('last day of last month')));
        $this->db->group_end();
        $this->db->where('wydatki.id_kupujacy',$id.''.$this->input->post("s_pracownik"));
        $this->db->from("platnosci");
        $query = $this->db->get();

        $resu = $query->result_array();
        $kartaz = $this->Karta_zeszly_miesiac($id);

        if (empty($resu[0]["swy"])) {
            $resu[0]["swy"] = 0;
        }
        if (!empty($kartaz)) {
            $k = $this->agregacja($kartaz);
        }
        if (!empty($resu)) {
            $w = $this->agregacja2($resu);
        }

        //var_dump($kartaz);
        $swoja = 0;
        $nie_roz = 0;
        $suma_karta = 0;

        if (!empty($k)) {
            foreach ($k as $karta) {
                if ($karta["typ_transakcji"] === "Płatność kartą") {
                    $suma_karta = bcadd($suma_karta, $karta["kwota"], 2);
                }
                if ($karta["typ_transakcji"] === "Wypłata z bankomatu") {
                    $suma_karta = bcadd($suma_karta, $karta["kwota"], 2);
                }


                foreach ($w as $wydatek) {

                    if ($karta["typ_transakcji"] === "Płatność kartą" && $wydatek["metoda_platnosci"] === "Karta") {
                        $calc = bcsub($wydatek["kwota_brutto"], abs($karta["kwota"]));
                        if ($calc > 0) {
                            $swoja = bcadd($swoja, $calc);
                        } else {
                            // $nie_roz = bcadd($nie_roz, $calc);
                        }
                    }

                    if ($karta["typ_transakcji"] === "Wypłata z bankomatu" && $wydatek["metoda_platnosci"] === "Gotówka") {
                        $calc = bcsub($wydatek["kwota_brutto"], abs($karta["kwota"]));
                        if ($calc > 0) {

                            $swoja = bcadd($swoja, $calc);
                        } else {

                            //$nie_roz = bcadd($nie_roz, $calc);
                        }
                    }
                }
            }
        }


        $found = array();
        if (!empty($kartaz)) {
            $nie_roz = bcsub($resu[0]["swy"], abs($suma_karta), 2);
            foreach ($kartaz as $karta) {

                if (in_array($karta["typ_transakcji"], self::akcetowane_metody_platnosci)) {
                    $found[] = ["data" => $this->test_b(abs($karta["kwota"]), self::karta_metoda_platnosci[$karta["typ_transakcji"]],$id.''.$this->input->post("s_pracownik")), "tid" => $karta["id_transakcji"]];
                }

            }
        }
        $rz = array();
        $rz_sum = 0;
        foreach ($found as $r) {
            if (!empty($r["data"])) {
                $rz[$r["data"]->id_wydatku] = ["kwota" => $r["data"]->kwota_brutto, "wydatek" => $r["data"]->id_wydatku, "dokument" => $r["data"]->dokument, "transakcja" => $r["tid"]];
                $rz_sum = bcadd($rz_sum, $r["data"]->kwota_brutto, 2);
            }
        }

        return ["wyniki" => $rz, "ze_swojej" => $swoja, "nie_roz" => abs($nie_roz), "znaleziono" => count($rz), "suma" => $rz_sum, "suma_wydatkow" => $resu[0]["swy"], "suma_karta" => abs($suma_karta)];

    }
    */
}
