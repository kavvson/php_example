<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 *
 */
class Adresy_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    private function _postalCode($postalCode) {
        return (bool) preg_match('/^\d{2}-\d{3}$/', $postalCode);
    }

    public function pokaz_adres($id)
    {
        $this->db->select("*");
        $this->db->where('id_adres', $id);
        $this->db->from("adresy");
        $query = $this->db->get();

        return $query->row();
    }
    /*
     * PARAM
     * direct - czy wywolanie jest miedzy modelami
     * 
     * POST
     * 
      inputUlica:1
      inputMiasto:1
      inputZip:1
     * 
     * RETURN
     * json_encode(array("regen" => CSRF_token, "response" => array("status" => 0/1, "message" => (int) ID/ (string) bledy)))
     */

    public function dodaj_adres($direct = TRUE) {

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $status = FALSE;

        $this->load->helper(array('form', 'url'));

        $this->load->library('form_validation');

        $this->form_validation->set_rules('inputMiasto', 'miasto', 'trim|required|min_length[5]|max_length[100]', array(
            'required' => 'Musisz podać miasto.',
            'min_length' => "Miasto musi mieć conajmniej 5 znaków",
            'max_length' => "Miasto może składać się z maksymalnie 100 znaków"
                )
        );
        $this->form_validation->set_rules('inputUlica', 'nazwę ulicy', 'trim|required|min_length[5]|max_length[100]', array(
            'required' => 'Musisz podać nazwę ulicy.',
            'min_length' => "Ulica musi mieć conajmniej 5 znaków",
            'max_length' => "Ulica może składać się z maksymalnie 100 znaków"
                )
        );
        $this->form_validation->set_rules('inputZip', 'Kod pocztowy', 'trim|required|min_length[5]|max_length[6]', array(
            'required' => 'Musisz podać Kod pocztowy.',
            'min_length' => "Kod pocztowy musi mieć conajmniej 5 znaków",
            'max_length' => "Kod pocztowy może składać się z maksymalnie 100 znaków"), 'callback__postalCode');

        $this->form_validation->set_message('_postalCode', 'Niepoprawny format kodu pocztowego');

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            // Nie ma błedów walidacji

            /* Dodawanie adresu do bazy danych */
            try {
                $this->db->trans_begin();
                $post_data = array(
                    'miasto' => $this->input->post('inputMiasto'),
                    'ulica' => $this->input->post('inputUlica'),
                    'kod_pocztowy' => $this->input->post('inputZip'),
                );
                $this->db->insert('adresy', $post_data);

                $message = $this->db->insert_id();
                $status = TRUE;
                $this->db->trans_commit();
            } catch (Exception $e) {
                $this->db->trans_rollback();
                log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
            }
        }

        if ($direct) {
            return json_encode(array("response" => array("status" => $status, "message" => $message)));
        } else {
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

}
