<?php

defined('BASEPATH') OR exit('No direct script access allowed');


class Premie_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    var $table = 'pracownik_premie';
    var $column_order = array(
        null,
        'zlorzyl',
        'na_rzecz',
        'kwota',
        'opis',
        'dodano',
        'status',
    ); //set column field database for datatable orderable
    var $column_search = array(); //set column field database for datatable searchable
    var $order = array('dodano' => 'asc'); // default order


    const statusy = [
        1 => "Zaakceptowany",
        2 => "W akceptracji",
        3 => "Odmowa",
    ];

    private function _get_datatables_query()
    {

        $this->db->select("*");
        $this->db->join('pracownicy', 'pracownik_premie.na_rzecz = pracownicy.id_pracownika', 'left');
        $this->db->join('users', 'pracownik_premie.zlorzyl = users.id', 'left');

        if ($this->input->post('s_pracownik')) {
            $this->db->where('`pracownik_premie.na_rzecz`', $this->input->post('s_pracownik'));
        }

        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'].'-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('dodano >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('dodano <=', date('Y-m-t', strtotime($query_date)));
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

        return $query->result();
    }

    public function agregacja($get)
    {
        $lacznie_brutto = 0;

        if (!empty($get)) {
            foreach ($get as $a) {
                $lacznie_brutto = bcadd($lacznie_brutto, $a['kwota'], 2);
            }
        }
        return
            array(
                'brutto' => $lacznie_brutto,
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


    function base64_url_encode($input)
    {
        return strtr(base64_encode($input), '+/=', '._-');
    }

    function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '._-', '+/='));
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

    public function lista_pracownikow($zid)
    {

        try {
            $this->db->trans_begin();
            $this->db->select('*');

            $this->db->from('pracownicy');
            $this->db->where('id_pracownika', $zid);
            $query = $this->db->get();
            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row;
        }
    }

    public function updatePremia($action, $premia_id)
    {
        try {
            $this->db->trans_begin();
            if ($action === "decline") {
                $platnosci = array(
                    'status' => 3,
                );
                $this->db->where('id_premii', $premia_id);
                $this->db->update('pracownik_premie', $platnosci);
                $msg = "Odmówiono";
            }
            if ($action === "accept") {
                $platnosci = array(
                    'status' => 1,
                );
                $this->db->where('id_premii', $premia_id);
                $this->db->update('pracownik_premie', $platnosci);
                $msg = "Akceptacja";
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                $msg = "Wystąpił błąd";
            } else {
                $this->db->trans_commit();
            }
            return $msg;

        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }
    }

    public function test($id_premii, $zlorzyl, $na_rzecz)
    {
        $this->db->select("*");

        $this->db->where('id_premii', $na_rzecz);
        $this->db->where('zlorzyl', $zlorzyl);
        $this->db->where('na_rzecz', $id_premii);
        $this->db->from("pracownik_premie");
        $query = $this->db->get();

        $resu = $query->row();

        return $resu;
    }

    public function Dodaj_Premie($pracownik)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = 0;
        $fid = NULL;

        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');

        $this->form_validation->set_rules('cf_premia_opis', 'opis', 'required|min_length[3]|max_length[100]', array(
            'min_length' => "Opis musi mieć conajmniej 3 znaki",
            'max_length' => "Opis może mieć najwyżej 100 znaków",
        ));


        $brutto = $this->custom_decimal($this->input->post('cf_premia_kwota'));

        if (!$brutto || strlen($brutto) < 1 || $brutto === "0.00") {
            $message = "Kwota nie jest liczbą";
        }

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();

        } else {

            try {
                $this->db->trans_begin();
                if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();

                    $this->load->model("Email_model", "notif");
                    $clin = $this->ion_auth->user()->row();
                    $narzecz = $this->lista_pracownikow($pracownik);
                    $wiadomosc = $clin->first_name . " " . $clin->last_name . " zarejestrował/a premię w wysokości " . $this->input->post('cf_premia_kwota') . " na rzecz " . $narzecz->imie . " " . $narzecz->nazwisko . " <br>";

                    /*
                     * Administracja nie musi wysyłać do siebie emali
                     */
                    if (in_array($clin->id, array(1, 3, 4))) {
                        $zaakceptowany = 1;
                    } else {
                        $zaakceptowany = 0;
                    }

                    $post_data = array(
                        'zlorzyl' => $clin->id,
                        'na_rzecz' => $narzecz->id_pracownika,
                        'kwota' => $brutto,
                        'opis' => $this->input->post("cf_premia_opis"),
                        'status' => $zaakceptowany,

                    );
                    //var_dump($post_data);
                    $this->db->insert('pracownik_premie', $post_data);
                    $fid = $this->db->insert_id();


                    if ($this->db->trans_status() === FALSE || strlen($message) > 0 || !is_numeric($fid)) {
                        $this->db->trans_rollback();
                    } else {
                        $this->db->trans_commit();
                        $message = "Dodano";
                        $status = 1;
                        if ($zaakceptowany == 0) {
                            $extras = $this->base64_url_encode($narzecz->id_pracownika) . "/" . $this->base64_url_encode($clin->id) . "/" . $this->base64_url_encode($fid);
                            $do_emaila = array(
                                'wiad' => $wiadomosc,
                                'accept_url' => base_url()."Place/Akceptacja_Premii/".$extras,
                                'decline_url' => base_url()."Place/Odmowa_Premii/".$extras,
                                'data' => date("Y-m-d"),
                                'opis' => $this->input->post("cf_premia_opis"),
                            );

                            $this->notif->nowa_premia($do_emaila);
                        }
                    }

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
            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message, "w" => $pracownik))));
    }

}