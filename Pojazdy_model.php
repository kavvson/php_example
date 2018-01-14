<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Pojazdy_model
 *
 * @author Kavvson
 *
 * TODO
 *
 * reasumująć trzeba dać opcje przypisania kierowyc do auta - opcje, że kierowca sie zmienia. bo jest tak, ze np ktos jest chory i ktos na czas nieobecnoscie bierze jego auto
 * i musi być podgląd historii zmiany kierowców
 * stawka_vat
 */
class Pojazdy_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    const stawka50 = 1;
    const stawka100 = 2;

    protected $_table_name = 'pojazdy p';

    public function dropdown_pojazdy()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $data = array();
        $getAd = $this->input->get('term');
        $limit = $this->input->get('page_limit');
        $this->db->select('p.poj_id,p.model,p.nr_rej,p.ubezp_oc,p.ubezp_ac,p.marka')
            ->from($this->_table_name)
            ->like('p.nr_rej', $getAd);

        $query = $this->db->limit($limit);
        $query = $this->db->get();

        $rowcount = $query->num_rows();

        $result = $query->result_array();
        if (count($result) > 0) {
            foreach ($result as $key => $value) {
                $data[] = array('id' => $value['poj_id'], 'text' => $value['nr_rej']);
            }
        }

        echo json_encode($data);
    }

    public function get_vehicle($method, $param = NULL, $param2 = NULL)
    {
        if ($method == "getPlate") {
            $this->db->select('p.poj_id,p.model,p.nr_rej,p.ubezp_oc,p.ubezp_ac,p.marka,p.przebieg')->from($this->_table_name)->where('p.nr_rej', $param);
        }
        if ($method == "getByID") {
            $this->db->select('p.poj_id,p.model,p.nr_rej,p.ubezp_oc,p.ubezp_ac,p.marka,p.przeglad,p.przebieg,SUM(pojazdy_przebiegi.wartosc) as przeje,p.stawka_vat,pliki.nazwa,pliki.data_dodania,pliki.path')
                ->join('pliki', 'p.spec = pliki.id', 'left')
                ->from($this->_table_name)
                ->join('pojazdy_przebiegi', 'pojazdy_przebiegi.FK_poj = p.poj_id')
                ->where('p.poj_id', $param);
        }
        if ($method == "exists") {
            $this->db->select('p.poj_id')->from($this->_table_name)->where('p.poj_id', $param);
        }
        if ($method == "serwisanta") {
            $this->db->select('pojazdy.*')
                ->from('pojaz_wykonawca map')
                ->join('pojazdy', 'pojazdy.poj_id = map.poj_id', 'right')
                ->where('map.wyk_id', $param);
        }
        if ($method == "isTaken") {
            $this->db->select('pojazdy.poj_id')
                ->from('pojaz_wykonawca map')
                ->join('pojazdy', 'pojazdy.poj_id = map.poj_id', 'right')
                ->where('map.wyk_id', $param)
                ->where('map.poj_id', $param2);
        }
        $query = $this->db->get();
        $rowcount = $query->num_rows();

        return array(
            'total' => $rowcount,
            'responce' => $query->result()
        );
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

    public function przebiegi($pojazd)
    {
        $this->db->select('wartosc,kiedy');
        $this->db->from('pojazdy_przebiegi');
        $this->db->where('FK_poj', $pojazd);
        $this->db->order_by('id', 'desc');
        $this->db->limit(1);

        $query = $this->db->get()->result_array();

        if(!empty($query)){
            return json_encode($query[0]);
        }else{
            return json_encode(array(""));
        }

    }


    public function dodaj_przebieg()
    {

        $status = 0;
        $message = "";
        $poj = $this->input->post("dot_pojazdu");
        $km = str_replace("km ", "", $this->input->post("inputKM"));

        $ostatni = json_decode($this->przebiegi($poj));
        if(!empty($ostatni->wartosc))
        {
            if($ostatni->wartosc >= $km)
            {
                $message = "Nie można podać mniejszego przebiegu od aktualnego.";
            }
        }

        if (strlen($message) == 0) {

            /* Dodawanie adresu do bazy danych */
            try {
                $this->db->trans_begin();

                $post_data = array(
                    'kiedy' => date("Y-m-d"),
                    'wartosc' => $km,
                    'FK_poj' => $poj
                );

                $this->db->insert('pojazdy_przebiegi', $post_data);

                $personID = $this->db->insert_id();
                $this->db->trans_commit();
            } catch (Exception $e) {
                $this->db->trans_rollback();
                log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
            }


            if (isset($personID) && is_numeric($personID)) {
                $status = TRUE;
                $message = "Dodano";
            } else {
                $status = FALSE;
                $message = "Błąd podczas dodawania przebiegu";
            }
        }


        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("response" => array("status" => $status, "message" => $message))));
    }

    public function wydatki($pojazd)
    {
        $extra ="";
        if (!isset($pojazd)) {
            $extra = ",nr_rej";
        }
        $this->db->select('data_zakupu,fk_pojazd,litry,brutto,`wydatki_kategorie`.`nazwa` as kat' . $extra);
        $this->db->from('pojazdy_wydatki');
        $this->db->join('wydatki_wpisy', 'pojazdy_wydatki.fk_wydatku = wydatki_wpisy.id_item', 'left');
        $this->db->join('wydatki', 'wydatki_wpisy.do_wydatku = wydatki.id_wydatku', 'left');
        $this->db->join('wydatki_kategorie', 'wydatki.kategoria = wydatki_kategorie.id_kat', 'left');
        if (isset($pojazd)) {
            $this->db->where('fk_pojazd', $pojazd);
        } else {
            $this->db->join('pojazdy', 'pojazdy_wydatki.fk_pojazd = pojazdy.poj_id', 'left');
        }
        //

        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('wydatki.data_zakupu >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('wydatki.data_zakupu <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }


        $query = $this->db->get()->result_array();
        //echo $this->db->last_query();

        $rows = array();

        $table = array();


        $table['cols'] = array(

            array('label' => 'str1', 'type' => 'string'),
            array('label' => 'str1', 'type' => 'string'),
           // array('role' => 'tooltip', 'type' => 'string','p'=>array('html'=>true)),
            array('label' => 'data1', 'type' => 'date'),
            array('label' => 'data2', 'type' => 'date')
        );

        foreach ($query as $r) {
            $date1 = new DateTime($r['data_zakupu']);
            $date2 = "Date(" . date_format($date1, 'Y') . ", " . ((int)date_format($date1, 'm') - 1) . ", " . date_format($date1, 'j') . ")";

            $date3 = new DateTime($r['data_zakupu']);
            $date3->modify("+ 1 day");
            $date4 = "Date(" . date_format($date3, 'Y') . ", " . ((int)date_format($date3, 'm') - 1) . ", " . date_format($date3, 'j') . ")";


            $temp = array();
            if (!isset($pojazd)) {
                $temp[] = array('v' => (string)$r['nr_rej']);
            } else {
                $temp[] = array('v' => (string)$r['kat']);
            }

            if ($r['kat'] === "Paliwo") {
                $alias = $r['litry'] . " L";
            } else {
                $alias = $r['kat']." - ".$r['brutto'] . " zł";
            }
            $temp[] = array('v' => (string)$alias);
           // $temp[] = array('v' => (string)"<div class='tooltip' style='width:200px'><strong>".$alias."</strong></div> ");
            $temp[] = array('v' => (string)$date2);
            $temp[] = array('v' => (string)$date4);

            $rows[] = array('c' => $temp);
        }
        $table['rows'] = $rows;
        if (empty($query)) {
            $date1 = new DateTime($query_date);
            $date2 = "Date(" . date_format($date1, 'Y') . ", " . ((int)date_format($date1, 'm') - 1) . ", " . date_format($date1, 'j') . ")";

            $date3 = new DateTime($query_date);
            $date3->modify("+ 1 day");
            $date4 = "Date(" . date_format($date3, 'Y') . ", " . ((int)date_format($date3, 'm') - 1) . ", " . date_format($date3, 'j') . ")";
            $temp = array();
            $temp[] = array('v' => (string)"Brak");
            $temp[] = array('v' => (string)"-");
           // $temp[] = array('v' => (string)"Brak informacji");
            $temp[] = array('v' => (string)$date2);
            $temp[] = array('v' => (string)$date4);
            $rows[] = array('c' => $temp);
            $table['rows'] = $rows;
        }


        echo json_encode($table);
    }

    public
    function pobierz_wydatki($method, $param = NULL, $param2 = NULL)
    {
        if ($method == "getPlate") {
            $this->db->select('p.poj_id,p.model,p.nr_rej,p.ubezp_oc,p.ubezp_ac,p.marka,p.przebieg')->from($this->_table_name)->where('p.nr_rej', $param);
        }
        if ($method == "getByID") {
            $this->db->select('p.poj_id,p.model,p.nr_rej,p.ubezp_oc,p.ubezp_ac,p.marka,p.przeglad,p.przebieg,SUM(pojazdy_przebiegi.wartosc) as przeje,p.stawka_vat,pliki.nazwa,pliki.data_dodania,pliki.path')
                ->join('pliki', 'p.spec = pliki.id', 'left')
                ->from($this->_table_name)
                ->join('pojazdy_przebiegi', 'pojazdy_przebiegi.FK_poj = p.poj_id')
                ->where('p.poj_id', $param);
        }
        if ($method == "exists") {
            $this->db->select('p.poj_id')->from($this->_table_name)->where('p.poj_id', $param);
        }
        if ($method == "serwisanta") {
            $this->db->select('pojazdy.*')
                ->from('pojaz_wykonawca map')
                ->join('pojazdy', 'pojazdy.poj_id = map.poj_id', 'right')
                ->where('map.wyk_id', $param);
        }
        if ($method == "isTaken") {
            $this->db->select('pojazdy.poj_id')
                ->from('pojaz_wykonawca map')
                ->join('pojazdy', 'pojazdy.poj_id = map.poj_id', 'right')
                ->where('map.wyk_id', $param)
                ->where('map.poj_id', $param2);
        }
        $query = $this->db->get();
        $rowcount = $query->num_rows();

        return array(
            'total' => $rowcount,
            'responce' => $query->result()
        );
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
    public  function Dodaj($opcja = "dodaj")
    {

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $status = FALSE;
        $message = "";
        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        // normalizacja numeru rej
        $nrRej = str_replace([' ', '-'], "", strtoupper($this->input->post("inputNr_rej")));


        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');

        $this->form_validation->set_rules('inputModel', 'Model', 'required|min_length[3]|max_length[50]', array(
            'required' => 'Musisz podać model pojazdu.',
            'min_length' => "Model pojazdu musi mieć conajmniej 3 znaków",
            'max_length' => "Model pojazdu może składać się z maksymalnie 50 znaków"
        ));

        $this->form_validation->set_rules('stawka_vat', '', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać stawkę vat.',
            'exact_length' => "Stawka vat musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola stawka vat może być tylko liczbą"
        ));


        $this->form_validation->set_rules('inputMarka', 'Marka', 'required|min_length[3]|max_length[50]', array(
                'required' => 'Musisz podać nazwisko pracownika.',
                'min_length' => "Marka pojazdu musi mieć conajmniej 3 znaków",
                'max_length' => "Marka pojazdu może składać się z maksymalnie 50 znaków"
            )
        );

        $this->form_validation->set_rules('inputNr_rej', 'Marka', 'required|min_length[5]|max_length[10]', array(
                'required' => 'Musisz podać nazwisko pracownika.',
                'min_length' => "Nr rejestracyjny pojazdu musi mieć conajmniej 5 znaków",
                'max_length' => "Nr rejestracyjny pojazdu może składać się z maksymalnie 10 znaków"
            )
        );

        $this->form_validation->set_rules('inputwartosc_pojazdu', '', 'required', array(
                'required' => 'Wartość pojazdu jest wymagana.'
            )
        );
        $ilosclitrow = $this->custom_decimal($this->input->post('inputwartosc_pojazdu'));
        if (!$ilosclitrow || $ilosclitrow === "0.00") {

            $message = "Proszę podać wartość pojazdu";
        }

        if($opcja == "modyfikacja") {
            $sprawdz = $this->get_vehicle("exists", $this->input->post('poj_id'));
            if ($sprawdz['total'] == 0) {
                $message = "Nie odnaleziono pojazdu w bazie";
            }
        }else{
            $sprawdz = $this->get_vehicle("getPlate", $nrRej);
            if ($sprawdz['total'] > 0) {
                $message = "Podany pojazd znajduje się już w bazie";
            }
        }


        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputUbezp_oc'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputUbezp_ac'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputPrzeglad'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }


        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            // Nie ma błedów walidacji
            // Sprawdzamy czy są jakieś błedy w adresie

            /*
             * Validacja skanu
             * Dodawanie skanu
             */

            $this->load->model("File_handler", "pliki");
            $this->pliki->fext("jpg|jpeg|pdf|png");
            $hook = $this->pliki->upload_file("inputSkan", "/pojazdy/" . $nrRej);
            // Błędy związane z FH
            if (isset(json_decode($hook)->result) && json_decode($hook)->result == "error") {
                $message = json_decode($hook)->msg;
            }
            // zwrócił sieżkę - dodano
            if ($hook) {
                $post_data = array(
                    'nazwa' => $_FILES["inputSkan"]['name'],
                    'path' => $hook
                );
                $this->db->insert('pliki', $post_data);
                $fid = $this->db->insert_id();

                if (!is_numeric($fid)) {
                    $message = "Nie dodano pliku - błąd";
                }
            }


            if (strlen($message) == 0) {

                /* Dodawanie adresu do bazy danych */
                try {
                    $this->db->trans_begin();

                    $post_data = array(
                        'model' => $this->input->post('inputModel'),
                        'nr_rej' => $nrRej,
                        'ubezp_oc' => $this->input->post('inputUbezp_oc'),
                        'ubezp_ac' => trim($this->input->post("inputUbezp_ac")),
                        'marka' => $this->input->post('inputMarka'),
                        'przeglad' => $this->input->post('inputPrzeglad'),
                        'stawka_vat' => $this->input->post('stawka_vat'),
                        'wartosc_pojazdu' => $ilosclitrow,


                    );
                    if ($hook) {
                        $post_data["spec"] = $fid;
                    }

                    if($opcja == "modyfikacja")
                    {
                        $this->db->where('poj_id', $this->input->post('poj_id'));
                        $this->db->update('pojazdy', $post_data);
                    }else{
                        $post_data["przebieg"] = $this->input->post('inputPrzebieg');
                        $this->db->insert('pojazdy', $post_data);
                    }


                    $personID = $this->db->insert_id();
                    $this->db->trans_commit();
                } catch (Exception $e) {
                    $this->db->trans_rollback();
                    log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
                }


                if (isset($personID) && is_numeric($personID)) {
                    $status = TRUE;
                    if($opcja == "modyfikacja"){
                        $message = "Powodzenie";
                    }else{
                        $message = "Dodano pojazd";
                    }

                } else {
                    $status = FALSE;
                    if($opcja == "modyfikacja"){
                        $message = "Błąd podczas modyfikacji";
                    }else{
                        $message = "Błąd podczas dodawania pojazdu";
                    }

                }
            }


            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(200)
                ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
        }


    }

}
