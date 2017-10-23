<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Kontrahent_model
 *
 * @author Kavvson
 */
class Kontrahent_model extends CI_Model {

    // charakter prawny kontrahenta
    const osoba_fizyczna = 1;
    const spolka = 2;

    public function __construct() {
        parent::__construct();
    }

    protected function usun_adres_korespondencyjny($adres_id) {

        if ($this->input->post('ad_kores') == 1 && !empty($this->input->post('fk_adres_kor'))) {
            // usuwanie
            $this->db->delete('adresy', array('id_adres' => $adres_id));  // Produces: // DELETE FROM mytable  // WHERE id = $id
            return TRUE;
        }
        return FALSE;
    }

    public function Edytuj_kontrahenta() {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $status = FALSE;

        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );
        $message = "";
        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');

        $nrb = $this->input->post("kli_Bank");
        $nrb = str_replace(" ", "", $nrb);

        $krs = $this->input->post("kli_KRS");
        $krs = str_replace(" ", "", $krs);

        $custom = $this->input->post();
        $custom['nbr'] = $nrb;
        $custom['krs'] = $krs;

        $this->form_validation->set_data($custom);

        $this->form_validation->set_rules("nbr", 'Model', 'required|min_length[26]|max_length[26]', array(
            'required' => 'Musisz podać numer konta bankowego.',
            'min_length' => "Musi miec dokładnie 26 liczb bez spacji",
            'max_length' => "Musi miec dokładnie 26 liczb bez spacji"
        ));
        $this->form_validation->set_rules("kli_c_name", 'Nazwa kontrahenta', 'required|min_length[2]|max_length[100]', array(
            'required' => 'Musisz podać nazwę kontrahenta',
            'min_length' => "kontrahent musi miec conajmniej 2 znaki",
            'max_length' => "kontrahent musi miec najwyżej 100 znaków",
        ));

        $this->form_validation->set_rules("kli_Spec", '', 'required|min_length[2]|max_length[80]', array(
            'required' => 'Musisz podać nazwę branży',
            'min_length' => "branża musi miec conajmniej 2 znaki",
            'max_length' => "branża musi miec najwyżej 80 znaków",
        ));

        $this->form_validation->set_rules("kli_c_main_miasto", '', 'required|min_length[2]|max_length[80]', array(
            'required' => 'Musisz podać miasto',
            'min_length' => "miasto musi miec conajmniej 2 znaki",
            'max_length' => "miasto musi miec najwyżej 80 znaków",
        ));
        $this->form_validation->set_rules("kli_c_main_ulica", '', 'required|min_length[2]|max_length[80]', array(
            'required' => 'Musisz podać ulicę',
            'min_length' => "ulica musi miec conajmniej 2 znaki",
            'max_length' => "ulica musi miec najwyżej 80 znaków",
        ));
        // Nie wymagane
        $this->form_validation->set_rules("kli_c_kor_miasto", '', 'min_length[2]|max_length[80]', array(
            'min_length' => "miasto musi miec conajmniej 2 znaki",
            'max_length' => "miasto musi miec najwyżej 80 znaków",
        ));
        $this->form_validation->set_rules("kli_c_kor_ulica", '', 'min_length[2]|max_length[80]', array(
            'min_length' => "ulica musi miec conajmniej 2 znaki",
            'max_length' => "ulica musi miec najwyżej 80 znaków",
        ));

        $this->form_validation->set_rules('char_prawny', 'char_prawny', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać charakter prawny.',
            'exact_length' => "Charakter prawny musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola charakter prawny może być tylko liczbą"
        ));

        $this->form_validation->set_rules('ad_kores', 'ad_kores', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz wybrać adres korenspondencyjny.',
            'exact_length' => "Adres korenspondencyjny musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola adres korenspondencyjny może być tylko liczbą"
        ));

        if ($this->input->post("char_prawny") == 2) {
            $this->form_validation->set_rules("krs", '', 'required|min_length[10]|max_length[10]|alpha_numeric', array(
                'required' => 'Musisz podać krs',
                'min_length' => "krs musi miec conajmniej 10 cyfr",
                'max_length' => "krs musi miec najwyżej 10 cyfr",
                'alpha_numeric' => "nr krs powinien składać się tylko z cyfr"
            ));
        }
        $this->form_validation->set_rules("kli_c_main_phone", '', 'required|min_length[7]|max_length[35]', array(
            'required' => "Proszę podać numer telefonu",
            'min_length' => "nr telefonu musi miec conajmniej 7 cyfr",
            'max_length' => "nr telefonu musi miec najwyżej 35 cyfr",
        ));

        $nip = $this->validatenip($this->input->post("kli_Nip"));
        $regon = $this->validateregon9($this->input->post("kli_Regon"));

        $zc_main = $this->postalCode($this->input->post("kli_c_main_zip"));
        $zc_kontaktowy = $this->postalCode($this->input->post("kli_c_kor_zip"));

        if (
                (
                !empty($this->input->post("kli_c_kor_miasto")) ||
                !empty($this->input->post("kli_c_kor_ulica")) ||
                !empty($this->input->post("kli_c_kor_zip"))
                ) && (
                empty($this->input->post("kli_c_kor_miasto")) ||
                empty($this->input->post("kli_c_kor_ulica")) ||
                empty($this->input->post("kli_c_kor_zip"))
                )
        ) {
            $message = "Wypełnij wszystkie pola adresu korespondencyjnego";
        }
        if (!$zc_main) {
            $message = "Nieprawidłowy kod pocztowy";
        }

        if (!empty($this->input->post("kli_c_kor_zip"))) {
            if (!$zc_kontaktowy) {
                $message = "Nieprawidłowy kod pocztowy";
            }
        }

        if (!$nip) {
            $message = "Nieprawidłowy nip";
        }
        if (!$regon) {
            $message = "Nieprawidłowy regon";
        }

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            if (strlen($message) == 0) {

                // Dodawanie adresu
                try {
                    $this->db->trans_begin();
                    $post_data = array(
                        'miasto' => $this->input->post('kli_c_main_miasto'),
                        'ulica' => $this->input->post('kli_c_main_ulica'),
                        'kod_pocztowy' => $this->input->post('kli_c_main_zip'),
                    );
                    $this->db->where('id_adres', $this->input->post('fk_adres'));
                    $this->db->update('adresy', $post_data);

                    //$adresID = $this->db->insert_id();
                    $adr_Kor = "";
                    // Opcjonalnie: Dodawanie adresu kontaktowego
                    if (
                            !empty($this->input->post("kli_c_kor_miasto")) &&
                            !empty($this->input->post("kli_c_kor_ulica")) &&
                            !empty($this->input->post("kli_c_kor_zip"))
                    ) {
                        if (!(empty($this->input->post('fk_adres_kor')))) {
                            $kor_data = array(
                                'miasto' => $this->input->post('kli_c_kor_miasto'),
                                'ulica' => $this->input->post('kli_c_kor_ulica'),
                                'kod_pocztowy' => $this->input->post('kli_c_kor_zip'),
                            );
                            $this->db->where('id_adres', $this->input->post('fk_adres_kor'));
                            $this->db->update('adresy', $kor_data);
                        } else {
                            // nowy adres
                            $kor_datai = array(
                                'miasto' => $this->input->post('kli_c_kor_miasto'),
                                'ulica' => $this->input->post('kli_c_kor_ulica'),
                                'kod_pocztowy' => $this->input->post('kli_c_kor_zip'),
                            );

                            $this->db->insert('adresy', $kor_datai);
                            $adr_Kor = $this->db->insert_id();
                        }
                    }
                    $usunadkor = $this->usun_adres_korespondencyjny($this->input->post('fk_adres_kor'));
                    
                    // Dodawanie kontrahenta

                    $kontrahent_data = array(
                        'nazwa' => $this->input->post('kli_c_name'),
                        'nazwa_short' => $this->input->post('kli_c_name_short'),
                        'nip' => $this->input->post('kli_Nip'),
                        'regon' => $this->input->post('kli_Regon'),
                        'krs' => $this->input->post('kli_KRS'),
                        'spec' => $this->input->post('kli_Spec'),
                        'phone' => $this->input->post('kli_c_main_phone'),
                        'konto' => str_replace(" ", "", $this->input->post('kli_Bank')),
                        //'adr_Kor' => $adr_Kor,
                        'char_prawny' => $this->input->post("char_prawny")
                    );
                    if($usunadkor)
                    {
                         $kontrahent_data['adr_Kor'] = NULL;
                    }
                    if (!(empty($adr_Kor))) {
                        $kontrahent_data['adr_Kor'] = $adr_Kor;
                    }
                    $this->db->where('id_kontrahenta', $this->input->post('pk_kontrahent'));
                    $this->db->update('kontrahenci', $kontrahent_data);


                    $this->db->trans_commit();

                    $message = "Zmodyfikowano";
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

    /*
     * Get all kontrahenci
     */

    function get_all_kontrahenci() {
        $this->db->order_by('id_kontrahenta', 'desc');
        return $this->db->get('kontrahenci')->result_array();
    }

    public function postalCode($postalCode, $strong = false) {
        return (bool) preg_match('/^\d{2}-\d{3}$/', $postalCode);
    }

    public function pokaz_kontrahenta($f) {
        $this->db->select("kontrahenci.*,b.ulica,b.miasto,b.kod_pocztowy,b.id_adres,k.ulica as kor_ul,k.miasto as kor_miasto,k.kod_pocztowy as kor_zip,k.id_adres as kor_id_adres");
        $this->db->join('adresy b', 'kontrahenci.fkaddress = b.id_adres');
        $this->db->join('adresy k', 'kontrahenci.adr_Kor = k.id_adres', 'LEFT');
        $this->db->where('kontrahenci.id_kontrahenta', $f);
        $this->db->from("kontrahenci");
        $query = $this->db->get();

        return (!empty($query->result())) ? $query->result() : "";
    }

    public function validatenip($nip) {
        $nipWithoutDashes = preg_replace("/-/", "", $nip);
        $reg = '/^[0-9]{10}$/';
        if (preg_match($reg, $nipWithoutDashes) == false)
            return false;
        else {
            $digits = str_split($nipWithoutDashes);
            $checksum = (6 * intval($digits[0]) + 5 * intval($digits[1]) + 7 * intval($digits[2]) + 2 * intval($digits[3]) + 3 * intval($digits[4]) + 4 * intval($digits[5]) + 5 * intval($digits[6]) + 6 * intval($digits[7]) + 7 * intval($digits[8])) % 11;

            return (intval($digits[9]) == $checksum);
        }
    }

    public function validateregon9($regon) {
        $reg = '/^[0-9]{9}$/';
        if (preg_match($reg, $regon) == false)
            return false;
        else {
            $digits = str_split($regon);
            $checksum = (8 * intval($digits[0]) + 9 * intval($digits[1]) + 2 * intval($digits[2]) + 3 * intval($digits[3]) + 4 * intval($digits[4]) + 5 * intval($digits[5]) + 6 * intval($digits[6]) + 7 * intval($digits[7])) % 11;
            if ($checksum == 10)
                $checksum = 0;

            return (intval($digits[8]) == $checksum);
        }
    }

    /*
     * 55114020040000360270485519
     * 
      NIP: 725-18-01-126
      Regon: 472836141
      KRS: 0000045146
     * POLA
      Content-Disposition: form-data; name="kli_c_name"
      Content-Disposition: form-data; name="kli_Nip"
      Content-Disposition: form-data; name="kli_Regon"
      Content-Disposition: form-data; name="kli_KRS"
      Content-Disposition: form-data; name="kli_Bank"
      Content-Disposition: form-data; name="kli_c_main_phone"
      Content-Disposition: form-data; name="kli_Spec"
      Content-Disposition: form-data; name="kli_c_main_miasto"
      Content-Disposition: form-data; name="kli_c_main_ulica"
      Content-Disposition: form-data; name="kli_c_main_zip"
      Content-Disposition: form-data; name="kli_c_kor_miasto"
      Content-Disposition: form-data; name="kli_c_kor_ulica"
      Content-Disposition: form-data; name="kli_c_kor_zip"
     */

    public function Dodaj_kontrahenta() {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $status = FALSE;

        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );
        $message = "";
        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');

        $nrb = $this->input->post("kli_Bank");
        $nrb = str_replace(" ", "", $nrb);

        $krs = $this->input->post("kli_KRS");
        $krs = str_replace(" ", "", $krs);

        $custom = $this->input->post();
        $custom['nbr'] = $nrb;
        $custom['krs'] = $krs;

        $this->form_validation->set_data($custom);

        $this->form_validation->set_rules("nbr", 'Model', 'required|min_length[26]|max_length[26]', array(
            'required' => 'Musisz podać numer konta bankowego.',
            'min_length' => "Musi miec dokładnie 26 liczb bez spacji",
            'max_length' => "Musi miec dokładnie 26 liczb bez spacji"
        ));
        $this->form_validation->set_rules("kli_c_name", 'Nazwa kontrahenta', 'required|min_length[2]|max_length[100]', array(
            'required' => 'Musisz podać nazwę kontrahenta',
            'min_length' => "kontrahent musi miec conajmniej 2 znaki",
            'max_length' => "kontrahent musi miec najwyżej 100 znaków",
        ));

        $this->form_validation->set_rules("kli_Spec", '', 'required|min_length[2]|max_length[80]', array(
            'required' => 'Musisz podać nazwę branży',
            'min_length' => "branża musi miec conajmniej 2 znaki",
            'max_length' => "branża musi miec najwyżej 80 znaków",
        ));

        $this->form_validation->set_rules("kli_c_main_miasto", '', 'required|min_length[2]|max_length[80]', array(
            'required' => 'Musisz podać miasto',
            'min_length' => "miasto musi miec conajmniej 2 znaki",
            'max_length' => "miasto musi miec najwyżej 80 znaków",
        ));
        $this->form_validation->set_rules("kli_c_main_ulica", '', 'required|min_length[2]|max_length[80]', array(
            'required' => 'Musisz podać ulicę',
            'min_length' => "ulica musi miec conajmniej 2 znaki",
            'max_length' => "ulica musi miec najwyżej 80 znaków",
        ));
        // Nie wymagane
        $this->form_validation->set_rules("kli_c_kor_miasto", '', 'min_length[2]|max_length[80]', array(
            'min_length' => "miasto musi miec conajmniej 2 znaki",
            'max_length' => "miasto musi miec najwyżej 80 znaków",
        ));
        $this->form_validation->set_rules("kli_c_kor_ulica", '', 'min_length[2]|max_length[80]', array(
            'min_length' => "ulica musi miec conajmniej 2 znaki",
            'max_length' => "ulica musi miec najwyżej 80 znaków",
        ));

        $this->form_validation->set_rules('char_prawny', 'char_prawny', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać charakter prawny.',
            'exact_length' => "Charakter prawny musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola charakter prawny może być tylko liczbą"
        ));

        $this->form_validation->set_rules('ad_kores', 'ad_kores', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz wybrać adres korenspondencyjny.',
            'exact_length' => "Adres korenspondencyjny musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola adres korenspondencyjny może być tylko liczbą"
        ));

        if ($this->input->post("char_prawny") == 2) {
            $this->form_validation->set_rules("krs", '', 'required|min_length[10]|max_length[10]|alpha_numeric', array(
                'required' => 'Musisz podać krs',
                'min_length' => "krs musi miec conajmniej 10 cyfr",
                'max_length' => "krs musi miec najwyżej 10 cyfr",
                'alpha_numeric' => "nr krs powinien składać się tylko z cyfr"
            ));
        }
        $this->form_validation->set_rules("kli_c_main_phone", '', 'required|min_length[7]|max_length[35]', array(
            'required' => "Proszę podać numer telefonu",
            'min_length' => "nr telefonu musi miec conajmniej 7 cyfr",
            'max_length' => "nr telefonu musi miec najwyżej 35 cyfr",
        ));

        $nip = $this->validatenip($this->input->post("kli_Nip"));
        
        
        //$regon = $this->validateregon9($this->input->post("kli_Regon"));

        $zc_main = $this->postalCode($this->input->post("kli_c_main_zip"));
        $zc_kontaktowy = $this->postalCode($this->input->post("kli_c_kor_zip"));

        if (
                (
                !empty($this->input->post("kli_c_kor_miasto")) ||
                !empty($this->input->post("kli_c_kor_ulica")) ||
                !empty($this->input->post("kli_c_kor_zip"))
                ) && (
                empty($this->input->post("kli_c_kor_miasto")) ||
                empty($this->input->post("kli_c_kor_ulica")) ||
                empty($this->input->post("kli_c_kor_zip"))
                )
        ) {
            $message = "Wypełnij wszystkie pola adresu korespondencyjnego";
        }
        if (!$zc_main) {
            $message = "Nieprawidłowy kod pocztowy";
        }

        if (!empty($this->input->post("kli_c_kor_zip"))) {
            if (!$zc_kontaktowy) {
                $message = "Nieprawidłowy kod pocztowy";
            }
        }

        if (!$nip) {
            $message = "Nieprawidłowy nip";
        }
        if (!$regon) {
          //  $message = "Nieprawidłowy regon";
        }

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            if (strlen($message) == 0) {

                // Dodawanie adresu
                try {
                    $this->db->trans_begin();
                    $post_data = array(
                        'miasto' => $this->input->post('kli_c_main_miasto'),
                        'ulica' => $this->input->post('kli_c_main_ulica'),
                        'kod_pocztowy' => $this->input->post('kli_c_main_zip'),
                    );
                    $this->db->insert('adresy', $post_data);

                    $adresID = $this->db->insert_id();
                    $adr_Kor = "";
                    // Opcjonalnie: Dodawanie adresu kontaktowego
                    if (
                            !empty($this->input->post("kli_c_kor_miasto")) &&
                            !empty($this->input->post("kli_c_kor_ulica")) &&
                            !empty($this->input->post("kli_c_kor_zip"))
                    ) {
                        $kor_data = array(
                            'miasto' => $this->input->post('kli_c_kor_miasto'),
                            'ulica' => $this->input->post('kli_c_kor_ulica'),
                            'kod_pocztowy' => $this->input->post('kli_c_kor_zip'),
                        );
                        $this->db->insert('adresy', $kor_data);
                        $adr_Kor = $this->db->insert_id();
                    }
                    // Dodawanie kontrahenta

                    $kontrahent_data = array(
                        'nazwa' => $this->input->post('kli_c_name'),
                        'nip' => $this->input->post('kli_Nip'),
                        'regon' => $this->input->post('kli_Regon'),
                        'krs' => $this->input->post('kli_KRS'),
                        'spec' => $this->input->post('kli_Spec'),
                        'fkaddress' => $adresID,
                        'phone' => $this->input->post('kli_c_main_phone'),
                        'konto' => $this->input->post('kli_Bank'),
                        'adr_Kor' => $adr_Kor,
                        'char_prawny' => $this->input->post("char_prawny")
                    );
                    $this->db->insert('kontrahenci', $kontrahent_data);
                    $kontrahent_id = $this->db->insert_id();

                    $this->db->trans_commit();

                    $message = "Dodano";
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

    public function populate() {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $data = array();
        $getAd = $this->input->get('term');
        $limit = $this->input->get('page_limit');
        $this->db->select('id_kontrahenta as id,nazwa as text,nip,konto,phone,ulica,miasto,kod_pocztowy')
                ->from('kontrahenci')
                ->like('nazwa', $getAd);
        $this->db->join('adresy', 'kontrahenci.fkaddress = adresy.id_adres');

        $query = $this->db->limit($limit);
        $query = $this->db->get();

        $rowcount = $query->num_rows();

        $result = $query->result_array();
        if (count($result) > 0) {
            foreach ($result as $key => $value) {
                $data[] = array('id' => $value['id'], 'text' => $value['text'], 'all' => $value);
            }
        }
        // return the result in json
        echo json_encode($data);
    }

}
