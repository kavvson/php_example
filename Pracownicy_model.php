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
            ->where('isInactive', 0);
        $this->db->group_start();
        $this->db->like('imie', $getAd)->or_like('nazwisko', $getAd);
        $this->db->group_end();

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
            $this->db->order_by('nazwisko', 'asc');
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

    /*
     * admin
     * status:1 // 1 aktualnie nieaktywny 0 akt. akt
       pracownik:2
     */
    public function aktywacja_pracownika()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $status = FALSE;
        $message = "";

        $datadezaktywacji = date('Y-m-d');
        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');

        $this->form_validation->set_rules('status', 'status', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'status podać rejon.',
            'exact_length' => "status musi mieć dokładnie 1 znak",
            'alpha_numeric' => "status może być tylko liczbą"
        ));

        $this->form_validation->set_rules('pracownik', 'pracownik', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać pracownika.',
            'alpha_numeric' => "pracownik może być tylko liczbą"
        ));
        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            try {
                $this->db->trans_begin();

                $prac = $this->input->post("pracownik");
                $sta = $this->input->post("status");

                if ($sta) {
                    $target_status = 0;
                } else {
                    $target_status = 1;
                }
                $post_data = array(
                    'isInactive' => $target_status,
                );
                if ($target_status === 1) {
                    $post_data['nieaktywny_od'] = $datadezaktywacji;
                }
                $this->db->where('id_pracownika', $prac);
                $this->db->update('pracownicy', $post_data);

                $this->db->trans_commit();
                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                } else {
                    $status = 1;
                }
            } catch (Exception $e) {
                $this->db->trans_rollback();
                log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
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


    public function dodaj_pracownika($rodzaj = "dodawanie", $pracownik = null, $adres = null)
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

        $this->form_validation->set_rules('inputStaz', 'numer konta', 'required|trim|alpha_numeric', array(
                'alpha_numeric' => "Staż może  składać się tylko z cyfr",
                'required' => "Musisz podać okres stażu",
            )
        );
        $this->form_validation->set_rules('inputStazData', 'numer konta', 'required|trim', array(
                'required' => "Musisz podać datę rozpoczęcia pracy pracownika",
            )
        );

        /*
         * inputStazData
         */
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputStazData'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            // Nie ma błedów walidacji
            // Sprawdzamy czy są jakieś błedy w adresie

            $this->load->model("Adresy_model", "adresy");


            if ($rodzaj === "dodawanie") {
                $adres = json_decode($this->adresy->dodaj_adres());
            } else {
                $adres = json_decode($this->adresy->modyfikuj_adres($adres));
            }

            // Jeżeli jest błąd w adresie pokaż
            if (!$adres->response->status) {
                $message = $adres->response->message;
            } else {


                // dostaliśmy ID adresu, można podpiąć go do klienta
                $adresid = $adres->response->message;

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
                        'staz' => $this->input->post('inputStaz'),
                        'staz_dodano' => $this->input->post('inputStazData'),
                    );
                    if ($rodzaj === "edycja") {
                        $this->db->where('id_pracownika', $pracownik);
                        $this->db->update('pracownicy', $post_data);
                    } else {
                        $this->db->insert('pracownicy', $post_data);
                        $personID = $this->db->insert_id();
                    }

                    if ($this->db->trans_status() === FALSE || strlen($message) > 0 || !is_numeric($adresid)) {
                        $this->db->trans_rollback();
                    } else {
                        $this->db->trans_commit();
                        if ($rodzaj === "dodawanie") {
                            if (isset($personID) && is_numeric($personID)) {
                                $status = TRUE;
                                $message = "Dodano pracownika";
                            } else {
                                $status = FALSE;
                                $message = "Błąd podczas dodawania pracownika";
                            }

                        } else {
                            $status = TRUE;
                            $message = "Sukces";
                        }
                    }

                } catch (Exception $e) {
                    $this->db->trans_rollback();
                    log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
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
            $this->db->select('id_pracownika,CONCAT(imie," ",nazwisko) as kto,COALESCE(period_diff(date_format(DATE_ADD(w_systemie_od, INTERVAL staz MONTH), \'%Y%m\'),date_format(w_systemie_od, \'%Y%m\')),0) as months');
            $this->db->where('isInactive', 0);
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

        $this->db->select("
            sum(do_wyplaty) as kwota,
            sum(zus_pracownik) as zus_pracownik,
            sum(zus_pracodawca) as zus_pracodawca,
            fk_prac
        ");
        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';
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
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

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
        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';

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

        $this->db->select("
            sum(kwota) as kwota,
            fk_pracownik
        ");
        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';

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
        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';

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
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

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
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

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

        $this->db->select("
            sum(kwota) as kwota,
            na_rzecz
        ");
        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';

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

        $this->db->select("
            sum(kwota) as kwota,
            fk_pracownik
        ");
        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';

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
        //  echo $this->db->last_query();
        return $re;
    }

    public function Potracenia_raport_caly()
    {

        $this->db->select("
            sum(kwota) as kwota,
            fk_pracownik
        ");
        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';

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



    public function Udzial_w_przychodzie_caly()
    {
        $this->db->select("przychody_udzialy.fk_pracownik,sum(round(przychody_udzialy.udzial * przychody.netto) / 100) as kwne");
        $this->db->join("przychody", "przychody_udzialy.fk_przychodu = przychody.id_przychodu", 'left');

        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';

            $this->db->group_start();
            $this->db->where('przychody.sprzedano >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('przychody.sprzedano <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->group_by('przychody_udzialy.fk_pracownik');
        $this->db->from("przychody_udzialy");
        $query = $this->db->get()->result();
        $re = array();
        foreach ($query as $res) {
            $re[$res->fk_pracownik] =
                array(
                    "kwne" => $res->kwne,
                );
        }

        return $re;
    }

    public function Wydatki_raport_caly()
    {

        $this->db->select("
            sum(kwota_brutto) as kwota,
            id_kupujacy
        ");

        if ((isset($_POST['m']) && $_POST['m'] >= 1 && $_POST['m'] <= 12) &&
            (isset($_POST['y']) && $_POST['y'] >= 2017 && $_POST['y'] <= 2050)) {

            $query_date = $_POST['y'] . '-' . $_POST['m'] . '-01';

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



    public function Doreki_raport($id)
    {
        $this->db->select("
            sum(kwota) as kwota,
        ");
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

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
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

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



    public function raport_plac()
    {

        error_reporting(0);

        $place = $this->Place_raport_caly();
        $delegacje = $this->Delegacje_raport_caly();

        $premie = $this->Premie_raport_caly();
        $doreki = $this->Doreki_raport_caly();


        $umowy = $this->Umowy_raport_caly();

        $wydatki = $this->Wydatki_raport_caly();

        $potracenia = $this->Potracenia_raport_caly();

        //   $oplaconeGotowka = $this->PobierzOplaconeCale(1);
        //   $oplaconePrzelew = $this->PobierzOplaconeCale(2);

        function ret($var)
        {
            return isset($var) ? $var : $var = "0.00";
        }

        $p = $this->Wszyscy_pracownicy();

        foreach ($p as $prac) {

            $umowykwotazus_pracownik = $umowy[$prac["id_pracownika"]]["zus_pracownik"];
            $umowykwotazus_pracodawca = $umowy[$prac["id_pracownika"]]["zus_pracodawca"];
            $placezus_pracownik = $place[$prac["id_pracownika"]]["zus_pracownik"];
            $placezus_pracodawca = $place[$prac["id_pracownika"]]["zus_pracodawca"];

            $totalzuspracownik = bcadd($placezus_pracownik, $umowykwotazus_pracownik, 2);
            $totalzuspracodawca = bcadd($placezus_pracodawca, $umowykwotazus_pracodawca, 2);


            $nareke = bcadd($nareke, $place[$prac["id_pracownika"]]["kwota"], 2);
            $nareke = bcadd($nareke, $umowy[$prac["id_pracownika"]]["kwota"], 2);
            $nareke = bcadd($nareke, $delegacje[$prac["id_pracownika"]]["kwota"], 2);
            $nareke = bcadd($nareke, $premie[$prac["id_pracownika"]]["kwota"], 2);
            $nareke = bcadd($nareke, $doreki[$prac["id_pracownika"]]["kwota"], 2);
            $nareke = bcsub($nareke, $potracenia[$prac["id_pracownika"]]["kwota"], 2);

            $kosztpracodawcy = bcadd($kosztpracodawcy, bcadd($totalzuspracodawca, $totalzuspracownik, 2), 2);
            $kosztpracodawcy = bcadd($kosztpracodawcy, $nareke, 2);


            $obrot = bcadd($kosztpracodawcy, $wydatki[$prac["id_pracownika"]]["kwota"], 2);

            /*
             * <td>" . ret($oplaconeGotowka[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td>" . ret($oplaconePrzelew[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td><button type=\"button\" data-id=\"".$prac["id_pracownika"]."\" data-toggle=\"modal\" data-target=\"#oplacGotowke\">Opłać gotówka</button>
                                <button type=\"button\" data-id=\"".$prac["id_pracownika"]."\" data-toggle=\"modal\" data-target=\"#oplacPrzelew\">Opłać przelew</button></td>
             */
            echo "<tr>
                   <td>" . $prac["kto"] . "</td>
                   <td>" . ret($place[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td>" . ret($umowy[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td>" . ret($delegacje[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td>" . ret($premie[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td>" . ret($doreki[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td>" . ret($nareke) . "</td>
                                <td>" . ret($wydatki[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td>" . ret($potracenia[$prac["id_pracownika"]]["kwota"]) . "</td>
                                <td>" . ret($totalzuspracownik) . "</td>
                                <td>" . ret($totalzuspracodawca) . "</td>
                                <td>" . ret(bcadd($totalzuspracodawca, $totalzuspracownik, 2)) . "</td>
                                <td>" . ret($kosztpracodawcy) . "</td>
                                <td>" . ret($obrot) . "</td>
                                </tr>";
            $umowykwotazus_pracownik = 0;
            $umowykwotazus_pracodawca = 0;
            $placezus_pracownik = 0;
            $placezus_pracodawca = 0;
            $totalzuspracownik = 0;
            $totalzuspracodawca = 0;
            $nareke = 0;
            $kosztpracodawcy = 0;
        }
    }

}
