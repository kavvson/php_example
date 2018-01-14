<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 *
 */
class Pojazdypliki_model extends CI_Model
{

    public $rodzaje_plikow = array(
        '1' => "UbezpieczenieOC",
        '2' => "UbezpieczenieAC",
        '3' => "Przegląd"
    );
    protected $table = "pojazdy_pliki";

    public function __construct()
    {
        parent::__construct();
    }

    /*
     *   ["ftype"]=>
          string(1) "1"
          ["inputData"]=>
          string(10) "2017-11-17"
    + FILE
     */

    public function zalacz_plik($do_pojazdu)
    {

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = 0;
        $fid = NULL;
        $this->load->helper(array('form', 'url'));
        $ftype = $this->input->post("ftype");

        $this->load->library('form_validation');

        if (empty($_FILES['inputSkan']['name'])) {
            $this->form_validation->set_rules('inputSkan', 'inputSkan', 'trim|required', array(
                'required' => 'Musisz dodać skan.',
            ));
        }

        $this->form_validation->set_rules('ftype', 'ftype', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać rodzaj pliku.',
            'alpha_numeric' => "Nie odnaleziono ftype"
        ));

        if (!in_array($ftype, array(1, 2, 3))) {
            $message = "Nie odnaleziono rodzaju pliku";
        }

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputData'))) {
            $message = "Nieprawidłowy format daty terminu płatności rrrr-mm-dd";
        }

        $this->load->model("Pojazdy_model", "k");
        $pojazd = $this->k->get_vehicle("getByID", $do_pojazdu)['responce'][0];

        if (empty($pojazd->poj_id)) {
            $message = "Nie odnaleziono pojazdu";
        }


        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();

        } else {
            try {

                $this->db->trans_begin();
                /*
                 * Validacja skanu
                 * Dodawanie skanu
                 */

                $this->load->model("File_handler", "pliki");
                $this->pliki->fext("jpg|jpeg|pdf|png");
                $hook = $this->pliki->upload_file("inputSkan", "/pojazdy/" . $pojazd->nr_rej . "/Dokumenty/" . $ftype);

                if (isset(json_decode($hook)->result) && json_decode($hook)->result == "error") {
                    $message = json_decode($hook)->msg;
                }
                // zwrócił sieżkę - dodano

                $post_data = array(
                    'nazwa' => $_FILES["inputSkan"]['name'],
                    'path' => $hook
                );
                $this->db->insert('pliki', $post_data);
                $fid = $this->db->insert_id();

                if (!is_numeric($fid)) {
                    $message = "Nie dodano pliku - błąd";
                }


                $post_data = array(
                    'typ_pliku' => $ftype,
                    'fk_pojazd' => $ftype,
                    'fk_plik' => $fid,
                    'waznosc' => $this->input->post('inputData'),
                );
                $this->db->insert($this->table, $post_data);
                $fid = $this->db->insert_id();

                switch ($ftype) {
                    case 1:
                        $field = "ubezp_oc";
                        break;
                    case 2:
                        $field = "ubezp_ac";
                        break;
                    case 3:
                        $field = "przeglad";
                        break;
                }

                $upost = array(
                    $field =>$this->input->post('inputData'),
                );
                $this->db->where('poj_id',$do_pojazdu);
                $this->db->update('pojazdy',$upost);
                /*
                $wydatek = array(
                    'prof_skan' => $fid
                );
                $this->db->where('id_wydatku', $fk_wydatek);
                $this->db->update('wydatki', $wydatek);
                */


                if ($this->db->trans_status() === FALSE || strlen($message) > 0 || empty($fid)) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                    if (is_numeric($fid)) {
                        $message = "Dodano";
                    }
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

    /*
     * Zwraca ostatnie ubezpieczenie
     */
    public function pobierz_ostatnie_pliki($pojazd,$typ)
    {
        try {
            $this->db->trans_begin();

            $this->db->select("*");
            $this->db->from($this->table);
            $this->db->where("fk_pojazd", $pojazd);
            $this->db->where("typ_pliku", $typ);

            $this->db->join('pliki', 'pojazdy_pliki.fk_plik = pliki.id', 'left');
            $this->db->order_by("pojazd_id", "desc");
            $this->db->limit(1);

            $query = $this->db->get()->result_array();

            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }
        return $query;
    }

}
