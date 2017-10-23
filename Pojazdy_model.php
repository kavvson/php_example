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
  i musi być podgląd historii zmiany kierowców
 * stawka_vat
 */
class Pojazdy_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    const stawka50 = 1;
    const stawka100 = 2;

    protected $_table_name = 'pojazdy p';

    public function dropdown_pojazdy() {
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

    public function get_vehicle($method, $param = NULL, $param2 = NULL) {
        if ($method == "getPlate") {
            $this->db->select('p.poj_id,p.model,p.nr_rej,p.ubezp_oc,p.ubezp_ac,p.marka,p.przebieg')->from($this->_table_name)->where('p.nr_rej', $param);
        }
        if ($method == "getByID") {
            $this->db->select('p.poj_id,p.model,p.nr_rej,p.ubezp_oc,p.ubezp_ac,p.marka,p.przeglad,p.przebieg,SUM(pojazdy_przebiegi.wartosc) as przeje,p.spec')
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

    /*
     * 
      Content-Disposition: form-data; name="inputModel"
      Content-Disposition: form-data; name="inputNr_rej"
      Content-Disposition: form-data; name="inputMarka"
      Content-Disposition: form-data; name="inputUbezp_oc"
      Content-Disposition: form-data; name="inputUbezp_ac"
      Content-Disposition: form-data; name="inputPrzeglad"


     */

    public function Dodaj() {

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

        $sprawdz = $this->get_vehicle("getPlate", $nrRej);
        if ($sprawdz['total'] > 0) {
            $message = "Podany pojazd znajduje się już w bazie";
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
                        'przebieg' => $this->input->post('inputPrzebieg'),
                        'stawka_vat' => $this->input->post('stawka_vat')
                    );
                    if ($hook) {
                        $post_data["spec"] = $fid;
                    }
                    $this->db->insert('pojazdy', $post_data);

                    $personID = $this->db->insert_id();
                    $this->db->trans_commit();
                } catch (Exception $e) {
                    $this->db->trans_rollback();
                    log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
                }


                if (isset($personID) && is_numeric($personID)) {
                    $status = TRUE;
                    $message = "Dodano pojazd";
                } else {
                    $status = FALSE;
                    $message = "Błąd podczas dodawania pojazdu";
                }
            }


            return $this->output
                            ->set_content_type('application/json')
                            ->set_status_header(200)
                            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
        }

       
    }

}
