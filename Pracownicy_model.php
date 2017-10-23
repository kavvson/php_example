<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 *
 */
class Pracownicy_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function populate()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $data = array();
        $getAd = $this->input->get('term');
        $limit = $this->input->get('page_limit');
        $this->db->select('id_pracownika as id,CONCAT(imie, " ", nazwisko) as text')
            ->from('pracownicy')
            ->like('imie', $getAd)->or_like('nazwisko', $getAd);

        $query = $this->db->limit($limit);
        $query = $this->db->get();

        $rowcount = $query->num_rows();

        $result = $query->result_array();
        if (count($result) > 0) {
            foreach ($result as $key => $value) {
                $data[] = array('id' => $value['id'], 'text' => $value['text']);
            }
        } else {
            //$data[] = array('id' => '0', 'text' => 'No Products Found');
        }

        // return the result in json
        echo json_encode($data);
    }

    public function lista_pracownikow($zid = FALSE)
    {

        try {
            $this->db->trans_begin();
            $this->db->select('*');

            $this->db->from('pracownicy');
            if ($zid) {
                $this->db->where('id_pracownika', $zid);
                $this->db->join('adresy b', 'pracownicy.fk_adres = b.id_adres');
            }
            $this->db->join('rejony', 'rejony.id_rejonu = pracownicy.fk_rejon', 'left');
            $query = $this->db->get();
            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }
        if ($query->num_rows() > 0) {
            $row = $query->result_array();
            return $row;
        }
    }

    public function dodaj_pracownika()
    {

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $status = FALSE;

        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        $this->load->helper(array('form', 'url'));

        $this->load->library('form_validation');

        $this->form_validation->set_rules('inputImie', 'imię', 'trim|required|min_length[3]|max_length[35]', array(
            'required' => 'Musisz podać imię pracownika.',
            'min_length' => "Imię musi mieć conajmniej 3 znaków",
            'max_length' => "Imię może składać się z maksymalnie 35 znaków"
        ));
        $this->form_validation->set_rules('inputRejon', 'rejon', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać rejon.',
            'exact_length' => "Rejon musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Rejon może być tylko liczbą"
        ));

        $this->form_validation->set_rules('inputNazwisko', 'nazwisko', 'required|min_length[3]|max_length[70]', array(
                'required' => 'Musisz podać nazwisko pracownika.',
                'min_length' => "Nazwisko musi mieć conajmniej 3 znaków",
                'max_length' => "Nazwisko może składać się z maksymalnie 70 znaków"
            )
        );

        $this->form_validation->set_rules('inputTelefon', 'telefon służbowy', 'trim|min_length[7]|max_length[25]', array(
                'min_length' => "Telefon służbowy musi mieć conajmniej 7 znaków",
                'max_length' => "Telefon służbowy może składać się z maksymalnie 70 znaków"
            )
        );

        $this->form_validation->set_rules('inputTelefonPryw', 'telefon prywatny', 'trim|min_length[7]|max_length[25]', array(
                'min_length' => "Telefon prywatny musi mieć conajmniej 7 znaków",
                'max_length' => "Telefon prywatny może składać się z maksymalnie 25 znaków"
            )
        );

        $this->form_validation->set_rules('inputBank', 'numer konta', 'trim|exact_length[26]|alpha_numeric', array(
                'exact_length' => "Numer konta  musi dokładnie 26 znaków bez spacji",
                'alpha_numeric' => "Numer konta tylko składać się z cyfr"
            )
        );


        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            // Nie ma błedów walidacji
            // Sprawdzamy czy są jakieś błedy w adresie

            $this->load->model("Adresy_model", "adresy");
            $adres = json_decode($this->adresy->dodaj_adres());

            // Jeżeli jest błąd w adresie pokaż
            if (!$adres->response->status) {
                $message = $adres->response->message;
            } else {


                // dostaliśmy ID adresu, można podpiąć go do klienta
                $adresid = $adres->response->message;
                if (is_numeric($adresid)) {
                    /* Dodawanie adresu do bazy danych */
                    try {
                        $this->db->trans_begin();
                        $post_data = array(
                            'fk_rejon' => $this->input->post('inputRejon'),
                            'fk_adres' => $adresid,
                            'imie' => $this->input->post('inputImie'),
                            'konto' => trim($this->input->post("inputBank")),
                            'nazwisko' => $this->input->post('inputNazwisko'),
                            'telefon_sluzbowy' => $this->input->post('inputTelefon'),
                            'telefon_prywatny' => $this->input->post('inputTelefonPryw'),
                        );
                        $this->db->insert('pracownicy', $post_data);

                        $personID = $this->db->insert_id();
                        $this->db->trans_commit();
                    } catch (Exception $e) {
                        $this->db->trans_rollback();
                        log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
                    }
                } else {

                }

                if (isset($personID) && is_numeric($personID)) {
                    $status = TRUE;
                    $message = "Dodano pracownika";
                } else {
                    $status = FALSE;
                    $message = "Błąd podczas dodawania pracownika";
                }
            }
        }


        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
    }

    /* Total stat */

    public function Wszyscy_pracownicy()
    {
        try {
            $this->db->trans_begin();
            $this->db->select('id_pracownika,CONCAT(imie," ",nazwisko) as kto');

            $this->db->from('pracownicy');


            $query = $this->db->get();
            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }
        if ($query->num_rows() > 0) {
            $row = $query->result_array();
            return $row;
        }
    }

    public function Place_raport_caly()
    {
        if(!empty($_POST['customMonth'])){$_POST['customMonth'] = date("n");}
        if(!empty($_POST['customYear'])){$_POST['customYear'] = date("y");}
        $this->db->select("
            sum(do_wyplaty) as kwota,
            sum(zus_pracownik) as zus_pracownik,
            sum(zus_pracodawca) as zus_pracodawca,
            fk_prac
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('miesiac >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('miesiac <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->group_by('fk_prac');

        $this->db->from("pracownik_place");
        $query = $this->db->get()->result();
        $re = array();
        foreach ($query as $res) {
            //$re[$res["fk_prac"]]["Kwota"][] = $res["kwota"];
            $re[$res->fk_prac] =
                array(
                    //"prac" =>$res->fk_prac,
                    "kwota" => $res->kwota,
                    "zus_pracownik" => $res->zus_pracownik,
                    "zus_pracodawca" => $res->zus_pracodawca
                );
        }
        return $re;
        // var_dump($query);
    }

    /* Statystyka */

    public function Place_raport($id)
    {
        $this->db->select("
            sum(do_wyplaty) as kwota,
            sum(zus_pracownik) as zus_pracownik,
            sum(zus_pracodawca) as zus_pracodawca,
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('miesiac >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('miesiac <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->where("fk_prac", $id);
        $this->db->from("pracownik_place");
        $query = $this->db->get()->result();
        return $query;
        // var_dump($query);
    }

    public function Delegacje_raport($id)
    {
        $this->db->select("
            sum(kwota) as kwota,
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('dstart >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('dstart <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->where("fk_pracownik", $id);
        $this->db->from("pracownik_delegacje");
        $query = $this->db->get()->result();
        return $query;
        // var_dump($query);
    }

    public function Delegacje_raport_caly()
    {
       if(!empty($_POST['customMonth'])){$_POST['customMonth'] = date("n");}
        if(!empty($_POST['customYear'])){$_POST['customYear'] = date("y");}
        $this->db->select("
            sum(kwota) as kwota,
            fk_pracownik
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('dstart >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('dstart <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->group_by('fk_pracownik');
        $this->db->from("pracownik_delegacje");
        $query = $this->db->get()->result();
        $re = array();
        foreach ($query as $res) {

            $re[$res->fk_pracownik] =
                array(
                    "kwota" => $res->kwota,
                );
        }
        return $re;

    }

    public function Umowy_raport_caly()
    {

        $this->db->select("
           sum(do_wyplaty) as kwota,
            sum(zus_pracownik) as zus_pracownik,
            sum(zus_pracodawca) as zus_pracodawca,
            fk_pracownik
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('DATE(' . date('Y-m-01', strtotime($query_date)) . ') BETWEEN "data_rozpoczecia" AND "data_zakoczenia"', '', false);


            $this->db->group_end();
        }
        $this->db->group_by('fk_pracownik');
        $this->db->from("pracownik_umowy");
        $query = $this->db->get()->result();

        $re = array();
        foreach ($query as $res) {
            //$re[$res["fk_prac"]]["Kwota"][] = $res["kwota"];
            $re[$res->fk_pracownik] =
                array(
                    //"prac" =>$res->fk_prac,
                    "kwota" => $res->kwota,
                    "zus_pracownik" => $res->zus_pracownik,
                    "zus_pracodawca" => $res->zus_pracodawca
                );
        }
        return $re;
        // var_dump($query);
    }

    public function Umowy_raport($id)
    {
        $this->db->select("
           sum(do_wyplaty) as kwota,
            sum(zus_pracownik) as zus_pracownik,
            sum(zus_pracodawca) as zus_pracodawca,
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('DATE(' . date('Y-m-01', strtotime($query_date)) . ') BETWEEN "data_rozpoczecia" AND "data_zakoczenia"', '', false);


            $this->db->group_end();
        }
        $this->db->where("fk_pracownik", $id);
        $this->db->from("pracownik_umowy");
        $query = $this->db->get()->result();
        return $query;
        // var_dump($query);
    }

    public function Premie_raport($id)
    {
        $this->db->select("
            sum(kwota) as kwota,
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('dodano >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('dodano <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->where("na_rzecz", $id);
        $this->db->from("pracownik_premie");
        $query = $this->db->get()->result();
        return $query;
        // var_dump($query);
    }

    public function Premie_raport_caly()
    {
       if(!empty($_POST['customMonth'])){$_POST['customMonth'] = date("n");}
        if(!empty($_POST['customYear'])){$_POST['customYear'] = date("y");}
        $this->db->select("
            sum(kwota) as kwota,
            na_rzecz
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('dodano >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('dodano <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->group_by('na_rzecz');
        $this->db->from("pracownik_premie");
        $query = $this->db->get()->result();
        $re = array();
        foreach ($query as $res) {

            $re[$res->na_rzecz] =
                array(
                    "kwota" => $res->kwota,
                );
        }
        return $re;
    }

    public function Doreki_raport_caly()
    {
       if(!empty($_POST['customMonth'])){$_POST['customMonth'] = date("n");}
        if(!empty($_POST['customYear'])){$_POST['customYear'] = date("y");}
        $this->db->select("
            sum(kwota) as kwota,
            fk_pracownik
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('zarejestrowano >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('zarejestrowano <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->group_by('fk_pracownik');
        $this->db->from("pracownik_doreki");
        $query = $this->db->get()->result();
        $re = array();
        foreach ($query as $res) {

            $re[$res->fk_pracownik] =
                array(
                    "kwota" => $res->kwota,
                );
        }
        return $re;
    }

    public function Potracenia_raport_caly()
    {
       if(!empty($_POST['customMonth'])){
           $_POST['customMonth'] = date("n");
         }
        if(!empty($_POST['customYear'])){$_POST['customYear'] = date("y");}

        $this->db->select("
            sum(kwota) as kwota,
            fk_pracownik
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('kiedy >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('kiedy <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }

        $this->db->group_by('fk_pracownik');
        $this->db->from("pracownik_potracenia");
        $query = $this->db->get()->result();
        
        $re = array();
        foreach ($query as $res) {

            $re[$res->fk_pracownik] =
                array(
                    "kwota" => $res->kwota,
                );
        }
        return $re;
    }

    public function Wydatki_raport_caly()
    {
       if(!empty($_POST['customMonth'])){$_POST['customMonth'] = date("n");}
        if(!empty($_POST['customYear'])){$_POST['customYear'] = date("y");}
        $this->db->select("
            sum(kwota_brutto) as kwota,
            id_kupujacy
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('data_zakupu >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('data_zakupu <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->group_by('id_kupujacy');
        $this->db->from("wydatki");
        $query = $this->db->get()->result();
        $re = array();
        foreach ($query as $res) {
            $re[$res->id_kupujacy] =
                array(
                    "kwota" => $res->kwota,
                );
        }
        return $re;
    }

    public function PobierzOplaconeCale($type)
    {

       if(!empty($_POST['customMonth'])){$_POST['customMonth'] = date("n");}
        if(!empty($_POST['customYear'])){$_POST['customYear'] = date("y");}

        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->select("kwota,fk_pracownik");


            $this->db->group_start();
            $this->db->where('data >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('data <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        if ($type === 1) {
            $this->db->where('typ', 'Gotowka');
        } else {
            $this->db->where('typ', 'Przelew');
        }
        //  $this->db->group_by('fk_pracownik');

        $this->db->from("pracownik_platnosci");
        $query = $this->db->get()->result();
        $re = array();
        foreach ($query as $res) {
            $re[$res->fk_pracownik] =
                array(
                    "kwota" => $res->kwota,
                );
        }

        return $re;


    }


    public function Doreki_raport($id)
    {
        $this->db->select("
            sum(kwota) as kwota,
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('zarejestrowano >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('zarejestrowano <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->where("fk_pracownik", $id);
        $this->db->from("pracownik_doreki");
        $query = $this->db->get()->result();
        return $query;
        // var_dump($query);
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

    public function PobierzOplacone($id, $type)
    {
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $this->db->select("kwota");


            $this->db->group_start();
            $this->db->where('data >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('data <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
            if ($type === 1) {
                $this->db->where('typ', 'Gotowka');
            } else {
                $this->db->where('typ', 'Przelew');
            }
            $this->db->where("fk_pracownik", $id);
            $this->db->from("pracownik_platnosci");
            $query = $this->db->get()->row();
            return (empty($query)) ? 0 : $query->kwota;

        }
    }

    public function RozliczGotowke($id, $type)
    {
        $message = "";
        $update_id = null;

        if ((isset($_POST['mp']) && $_POST['mp'] >= 1 && $_POST['mp'] <= 12) &&
            (isset($_POST['yp']) && $_POST['yp'] >= 2017 && $_POST['yp'] <= 2050)) {

            $this->db->select("id");


            $query_date = $_POST['yp'] . '-' . $_POST['mp'] . '-01';

            $this->db->group_start();
            $this->db->where('data >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('data <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
            if ($type === 1) {
                $this->db->where('typ', 'Gotowka');
            } else {
                $this->db->where('typ', 'Przelew');
            }

            $this->db->where("fk_pracownik", $id);
            $this->db->from("pracownik_platnosci");
            $query = $this->db->get()->result();
            if (!empty($query[0]->id)) {
                $update_id = $query[0]->id;
            }

        }

        if ($type === 1) {
            $brutto = $this->custom_decimal($this->input->post('cf_oplac_gotowka'));
        } else {
            $brutto = $this->custom_decimal($this->input->post('cf_oplac_przelew'));
        }


        if (!$brutto) {
            $message = "Wartość brutto nie jest liczbą";
        }
        try {
            if (strlen($message) == 0) {
                $this->db->trans_begin();
                $post_data = array(
                    'data' => date($_POST['yp'] . '-' . $_POST['mp'] . '-d'),
                    'dodal' => $this->ion_auth->user()->row()->id,
                    'kwota' => $brutto,
                    'fk_pracownik' => $id
                );
                if ($type === 1) {
                    $post_data['typ'] = "Gotowka";
                } else {
                    $post_data['typ'] = "Przelew";
                }
                if (is_numeric($update_id)) {
                    //update
                    $this->db->where('id', $update_id);
                    $this->db->update('pracownik_platnosci', $post_data);
                    $idw = 1;
                } else {
                    $this->db->insert('pracownik_platnosci', $post_data);
                    $idw = $this->db->insert_id();
                }


            }


            if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
                if (isset($idw) && is_numeric($idw)) {
                    $status = TRUE;
                    $message = "Dodano";
                }
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
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

}
