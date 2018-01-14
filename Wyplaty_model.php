<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Wyplaty_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function custom_decimal($decimal)
    {
        $decimal = str_replace("Zł ", "", $decimal);
        $decimal = str_replace("dm3 ", "", $decimal);
        $decimal = str_replace(",", "", $decimal);


        if (preg_match('/^[0-9]+\.[0-9]{2}$/', $decimal) || is_numeric($decimal)) {
            return $decimal;
        } else {
            return FALSE;
        }
    }

    public function Dostepne_raporty()
    {
        $query = $this->db->query("SELECT month(data) as mm,YEAR(data)as yy FROM `pracownik_platnosci` GROUP BY month(data),year(data) ORDER BY data ASC");
        $query = $query->result_array();
        return $query;
    }

    public function Dodaj_wyplaty()
    {
        // var_dump($_POST);

        $pracownicy = $this->input->post("inputPracownik");
        $k_gotowka = $this->input->post("k_gotowka");
        $k_przelew = $this->input->post("k_przelew");

        $month = $this->input->post("mm");
        $rok = $this->input->post("yy");


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

        $this->form_validation->set_rules('inputPracownik[]', 'numer konta', 'required|trim|alpha_numeric', array(
                'alpha_numeric' => "Miesiąc może  składać się tylko z cyfr",
                'required' => "Musisz podać Miesiąc",
            )
        );

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {

            try {
                $this->db->trans_begin();
                foreach ($pracownicy as $key => $value) {

                    $today = date("Y-m-d");
                    if ((isset($month) && $month >= 1 && $month <= 12) &&
                        (isset($rok) && $rok >= 2017 && $rok <= 2050)) {
                        $query_date = $rok . '-' . $month . '-01';
                        $today = date('Y-m-d', strtotime($query_date));
                    } else {
                        $message = "Nie przekazano okresu";
                    }
                    $got_kw = $this->custom_decimal($k_gotowka[$key]);
                    $prze_kw = $this->custom_decimal($k_przelew[$key]);
                    $post_data[] = array(
                        'data' => $today,
                        'kwota_gotowki' => $got_kw,
                        'kwota_przelewu' => $prze_kw,
                        'oplacono_gotowke' => 0,
                        'oplacono_przelew' => 0,
                        'fk_pracownik' => $value,
                        'dodal' => $this->ion_auth->get_user_id(),
                    );

                    if ($prze_kw === false && $got_kw === false) {
                        $message = "Przyjanmniej jedna kwota musi być dodatnia";
                    }
                }


                $this->db->insert_batch('pracownik_platnosci', $post_data);


                if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();

                    $status = TRUE;
                    $message = "Dodano";

                }
            } catch (Exception $e) {
                $this->db->trans_rollback();
                log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
            }


        }

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
    }

    public function Rozlicz()
    {

        $oplac = $this->input->post("oplac");

        $month = $this->input->post("mm");
        $rok = $this->input->post("yy");


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

        $this->form_validation->set_rules('oplac[]', '', 'required|trim|alpha_numeric', array(
                'required' => "Brak pól do opłacenia",
            )
        );

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {

            try {
                $this->db->trans_begin();
                if ((isset($month) && $month >= 1 && $month <= 12) &&
                    (isset($rok) && $rok >= 2017 && $rok <= 2050)) {
                } else {
                    $message = "Nie przekazano okresu";
                }

                foreach ($oplac as $key => $value) {

                    $status_gotowki = 0;
                    $status_przelewu = 0;
                    if (isset($value['gotowka'][0])) {
                        $status_gotowki = 1;
                        $post_data[] = array(
                            'id' => $key,
                            'oplacono_gotowke' => $status_gotowki,
                        );
                    }
                    if (isset($value['przelew'][0])) {
                        $status_przelewu = 1;
                        $post_data[] = array(
                            'id' => $key,
                            'oplacono_przelew' => $status_przelewu,
                        );
                    }

                }
                $this->db->update_batch('pracownik_platnosci', $post_data, 'id');


                if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();

                    $status = TRUE;
                    $message = "Dodano";

                }
            } catch (Exception $e) {
                $this->db->trans_rollback();
                log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
            }


        }

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
    }

}
