<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Przychody
 *
 * @author Kavvson
 */
class Przychody_model extends CI_Model
{

    // Statusy
    const do_zaplaty = 1;
    const oplacony = 2;
    const czesciowo_oplacony = 3;

    public function __construct()
    {
        parent::__construct();
    }


    public function pobierz_wpisy_korekty($przychod)
    {

        $this->db->select("*");

        $this->db->join('korekty', 'korekty_dokument.fk_link = korekty.fk_przychod');
        $this->db->join('przychody_korekta', 'korekty.fk_wpis = przychody_korekta.id');

//
        $this->db->where('korekty_dokument`.`id', $przychod);
        $this->db->from("korekty_dokument");


        $query = $this->db->get();
        $re = array();
        $n = "";

        foreach ($query->result() as $r) {
            $re[$r->fk_wpis_prz]["new_pricenet"] = $r->new_pricenet;
            $re[$r->fk_wpis_prz]["new_pricebrut"] = $r->new_pricebrut;
            $re[$r->fk_wpis_prz]["new_pricevat"] = $r->new_pricevat;
            $re[$r->fk_wpis_prz]["newvat_type"] = $r->newvat_type;
            $re[$r->fk_wpis_prz]["new_vat"] = $r->new_vat;
            $re[$r->fk_wpis_prz]["new_jednostka"] = $r->new_jednostka;
            $re[$r->fk_wpis_prz]["new_quantity"] = $r->new_quantity;
            $re[$r->fk_wpis_prz]["id"] = $r->id;
            $re[$r->fk_wpis_prz]["row_korekta_net"] = bcmul($r->new_pricenet, $r->new_quantity, 2);
            $re[$r->fk_wpis_prz]["row_korekta_vat"] = bcmul($r->new_vat, $r->new_quantity, 2);
            $re[$r->fk_wpis_prz]["row_korekta_brutto"] = bcadd(bcmul($r->new_vat, $r->new_quantity, 2), bcmul($r->new_pricenet, $r->new_quantity, 2), 2);
            $n = $r->nazwa;
        }

        return array("re" => $re, "nazwa" => $n);
    }


    public function existsKorekta($param)
    {
        try {
            $this->db->trans_begin();
            $this->db->where('korekty_dokument.fk_link', $param);
            $this->db->from("korekty_dokument");
            $query = $this->db->get();

            $wartosci = $query->result_array();


            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }

        return (count($wartosci) >= 1) ? TRUE : FALSE;
    }

    public function korekta($param)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = 0;

        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');


        $this->form_validation->set_rules('inputBrutto2[]', 'rejon', 'decimal_check');

        $invoiceData = $this->input->post("data[InvoiceContent]");


        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            if (empty($invoiceData)) {
                $message = "Wprowadź conajmniej jedną pozycję";
            }

            if($this->existsKorekta($param)){
                $message = "Nie można dodać więcej niż jednej korekty";
            }
            foreach ($invoiceData as $index => $v) {

                $sw = $this->custom_decimal($v["netto"]);
                $dq = $this->custom_decimal($v["count"]);

                if (!$sw) {
                    $message = "Wartość netto nie jest liczbą";
                    break;
                }
                if (($v["vat"] < 0 || $v["vat"] > 99) || empty($v["vat"])) {
                    $message = "Procent vat nie jest liczbą ";
                    break;
                }
                if ($dq == FALSE) {
                    $message = "Ilość nie jest liczbą";
                    break;
                }
                if (empty($v["unit"])) {
                    $message = "Brak jednostki";
                    break;
                }
            }
            if (strlen($message) == 0) {
                $lacznie_brutto = 0;
                try {

                    $this->db->trans_begin();
                    $ntvat = 0;
                    $ntbrut = 0;
                    $ntnet = 0;
                    foreach ($invoiceData as $index => $v) {

                        if (!is_numeric($v["vat"])) {
                            $net = $this->custom_decimal($v["netto"]);
                            $wvat = 0;
                            $bbrutto = $net;
                        } else {
                            $net = $this->custom_decimal($v["netto"]);
                            $wvat = ($net * $v["vat"]) / 100;
                            $bbrutto = $wvat + $net;
                        }

                        $post_data = array(
                            'new_pricenet' => $net,
                            'new_pricebrut' => $bbrutto,
                            'new_pricevat' => $wvat,
                            'fk_wpis_prz' => $index, // ??
                            'new_quantity' => $v['count'],
                            'new_jednostka' => $v["unit"],
                        );
                        if (!is_numeric($v['vat'])) {
                            $post_data['newvat_type'] = $v["vat"];
                            $post_data['new_vat'] = 0;
                        } else {
                            $post_data['new_vat'] = $v["vat"];
                        }
                        //  $this->db->where('id_item', $index);
                        $this->db->insert('przychody_korekta', $post_data);
                        $added = $this->db->insert_id();

                        $korekty = array(
                            'fk_przychod' => $this->input->post("faktura_id"),
                            'fk_wpis' => $added,
                        );
                        $this->db->insert('korekty', $korekty);

                        $ntvat += $wvat * $v['count'];
                        $ntbrut += $bbrutto * $v['count'];
                        $ntnet += $net * $v['count'];

                    }


                    $post_data = array(
                        'fk_link' => $this->input->post('faktura_id'),
                        'nazwa' => $this->decode_json_f_nr($this->get_custom_k_nr($this->input->post("termin"))),
                        'nvat' => $ntvat,
                        'nbrut' => $ntbrut,
                        'nnet' => $ntnet
                    );

                    $this->db->insert('korekty_dokument', $post_data);
                    $status = 1;


                    if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                        $this->db->trans_rollback();
                    } else {
                        $this->db->trans_commit();
                        $message = "Dodano";
                    }

                } catch (Exception $e) {
                    $this->db->trans_rollback();
                    log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
                }
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

    public function edycja($param)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }


        $message = "";
        $status = 0;

        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');


        $this->form_validation->set_rules('inputBrutto2[]', 'rejon', 'decimal_check');
        $this->form_validation->set_rules('inputRejon2', 'rejon', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać rejon.',
            'alpha_numeric' => "Rejon może być tylko liczbą"
        ));

        $this->form_validation->set_rules('inputKontrahent2', 'kontrahent', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać kontrahenta.',
            'alpha_numeric' => "Wartość pola Kontrahent może być tylko liczbą"
        ));


        $this->form_validation->set_rules('inputOpis', 'opis', 'trim|min_length[3]|max_length[250]', array(
            'min_length' => "Opis musi mieć conajmniej 3 znaki",
            'max_length' => "Opis może mieć najwyżej 250 znaków",
        ));


        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputDatawystaw'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputDatasprze'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }

        if (empty($this->input->post('termin_platnosci'))) {
            $message = "Termin płatności nie jest datą";
        }

        $invoiceData = $this->input->post("data[InvoiceContent]");


        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            if (empty($invoiceData)) {
                $message = "Wprowadź conajmniej jedną pozycję";
            }
            foreach ($invoiceData as $index => $v) {

                $sw = $this->custom_decimal($v["netto"]);
                $dq = $this->custom_decimal($v["count"]);

                if (empty($v["name"])) {
                    $message = "Proszę podać nazwę";
                    break;
                }
                if (strlen($v["name"]) > 200) {
                    $message = "Nazwa może się składać max z 200 znaków";
                    break;
                }
                if (!$sw) {
                    $message = "Wartość netto nie jest liczbą";
                    break;
                }
                if (($v["vat"] < 0 || $v["vat"] > 99) || empty($v["vat"])) {
                    $message = "Procent vat nie jest liczbą ";
                    break;
                }
                if ($dq == FALSE) {
                    $message = "Ilość nie jest liczbą";
                    break;
                }
                if (empty($v["unit"])) {
                    $message = "Brak jednostki";
                    break;
                }
            }
            if (strlen($message) == 0) {
                $lacznie_brutto = 0;
                $lacznie_vat = 0;
                $lacznie_netto = 0;
                // [{"netto":"11.00","vat":"22","nazwa":"xa","ilosc":"1.00","jednostka":"m2"}]
                try {

                    $this->db->trans_begin();
                    foreach ($invoiceData as $index => $v) {

                        if (!is_numeric($v["vat"])) {
                            $net = $this->custom_decimal($v["netto"]);
                            $wvat = 0;
                            $bbrutto = $net;
                        } else {
                            $net = $this->custom_decimal($v["netto"]);
                            $wvat = ($net * $v["vat"]) / 100;
                            $bbrutto = $wvat + $net;
                        }

                        $post_data = array(
                            'nazwa' => $v["name"],
                            'ilosc' => $v["count"],
                            'netto' => $net,
                            'brutto' => $bbrutto,
                            'wartosc_vat' => $wvat,
                            'jednostka' => $v["unit"],
                        );
                        if (!is_numeric($v['vat'])) {
                            $post_data['typ_vat'] = $v["vat"];
                            $post_data['vat'] = 0;
                        } else {
                            $post_data['vat'] = $v["vat"];
                        }
                        $this->db->where('id_item', $index);
                        $this->db->update('przychody_wpisy', $post_data);
                        //$batch_insertIDS[] = $this->db->insert_id();
                        $lacznie_brutto += bcmul($bbrutto, $v["count"], 2);
                        $lacznie_netto += bcmul($net, $v["count"], 2);
                        $lacznie_vat += bcmul($wvat, $v["count"], 2);
                    }


                    // Dodawanie przychodu
                    if (is_numeric($this->input->post('termin_platnosci'))) {
                        $add = $this->input->post('termin_platnosci');
                        $termin_platnosci = date('Y-m-d', strtotime($this->input->post('inputDatawystaw') . ' + ' . $add . ' days'));
                        // inne
                        ///////
                    }

                    $post_data = array(
                        'id_rejonu' => $this->input->post('inputRejon2'),
                        'fk_kontrahent' => $this->input->post('inputKontrahent2'),
                        'dodal' => $this->ion_auth->user()->row()->id,
                        'z_dnia' => $this->input->post('inputDatawystaw'),
                        'sprzedano' => $this->input->post('inputDatasprze'),
                        'wartosc' => $lacznie_brutto,
                        'netto' => $lacznie_netto,
                        'vat_lacznie' => $lacznie_vat,
                        'uwagi' => $this->input->post('inputOpis'),
                        'termin_platnosci' => $termin_platnosci,
                        'ilosc_dni' => $this->input->post("termin_platnosci")
                    );
                    $this->db->where('id_przychodu', $this->input->post("faktura_id"));
                    $this->db->update('przychody', $post_data);
                    $status = 1;


                    $pps = array(
                        'status	' => Przychody_model::do_zaplaty,
                        'pozostala_kwota' => $lacznie_brutto,
                        'fk_przychodu' => $this->input->post("faktura_id"),
                    );
                    $this->db->where('id_platnosci', $this->input->post("platnosci[id_platnosci]"));
                    $this->db->update('przychody_platnosci', $pps);

                    if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                        $this->db->trans_rollback();
                    } else {
                        $this->db->trans_commit();

                        $message = "Zmodyfikowano";
                    }
                } catch (Exception $e) {
                    $this->db->trans_rollback();
                    log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
                }
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

    public function oplacFakture($dp)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = FALSE;
        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        $brutto = $this->custom_decimal($this->input->post('inputBrutto'));
        $dp = $this->input->post('dot_platnosci__');
        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');
        $this->form_validation->set_rules('dot_platnosci__', 'Dotyczy płatności', 'trim|required|alpha_numeric', array(
            'required' => '<<Nie ma numeru referencyjnego>>.',
            'alpha_numeric' => "Numer ref. może tylko składać się z cyfr"
        ));

        if (!$brutto) {
            $message = "Wartość brutto nie jest liczbą";
        }

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            try {

                $this->db->where('przychody_platnosci.id_platnosci', $dp);
                $this->db->from("przychody_platnosci");
                $query = $this->db->get();

                $wartosci = $query->row();

                $zaplacona_kwota = $wartosci->otrzymana_kwota;
                $pozostala_kwota = $wartosci->pozostala_kwota;

                if ($pozostala_kwota < $brutto) {
                    $message = "Pozostało do zapłaty " . $pozostala_kwota . " a chcesz zapłacić " . $brutto;
                }

                // sprawdzamy czy jest oplacona

                $czyOplacona = $pozostala_kwota - $brutto;
                $statusi = $wartosci->status;
                if ($czyOplacona == 0) {

                    $statusi = 2;
                }
                if ($czyOplacona > 0 && $pozostala_kwota > 0) {
                    $statusi = 3;
                }

                //var_dump($wartosci);
                if (strlen($message) == 0) {
                    $this->db->trans_begin();
                    $post_data = array(
                        'dot_platnosci' => $dp,
                        'kwota_wplacona' => $brutto,
                        'wplacil' => $this->ion_auth->user()->row()->id,
                    );

                    $this->db->insert('przychody_platnosci_historia', $post_data);
                    $idw = $this->db->insert_id();


                    $this->db->where('id_platnosci', $dp);
                    $this->db->set('status', $statusi);
                    if ($czyOplacona == 0) {

                        $this->db->set('oplacono', date('Y-m-d'));
                    }

                    $this->db->set('otrzymana_kwota', 'otrzymana_kwota+' . $brutto, FALSE);
                    $this->db->set('pozostala_kwota', 'pozostala_kwota-' . $brutto, FALSE);
                    $this->db->update('przychody_platnosci');
                    $this->db->trans_commit();
                    $message = "Dodano";
                    $status = 1;
                }
                /* koniec dodawania skanu */
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

    public function pobierz_pracownikow($id)
    {
        $this->db->select("przychody_udzialy.udzial,wartosc,przychody.wartosc,CONCAT(pracownicy.imie,' ', pracownicy.nazwisko) as pracownik");
        $this->db->join('przychody', 'przychody_udzialy.fk_przychodu = przychody.id_przychodu');
        $this->db->join('pracownicy', 'przychody_udzialy.fk_pracownik = pracownicy.id_pracownika');
        $this->db->order_by("udzial", "DESC");

        $this->db->where('przychody.id_przychodu', $id);
        $this->db->from("przychody_udzialy");
        $query = $this->db->get();

        return $query->result();
    }

    /*
     * $this->db->select("*,datediff(`przychody`.`termin_platnosci`,NOW()) as ddif,datediff(`przychody`.`termin_platnosci`,przychody_platnosci.oplacono) as pdif,rejony.nazwa as rejont,"
      . "kontrahenci.nazwa as kontrah,`przychody`.`termin_platnosci` as termin,przychody_platnosci.oplacono as oplacono,");
      $this->db->join('przychody', 'przychody_platnosci.fk_przychodu = przychody.id_przychodu');
      $this->db->join('rejony', 'przychody.id_rejonu = rejony.id_rejonu', 'left');
      $this->db->join('kontrahenci', 'przychody.fk_kontrahent = kontrahenci.id_kontrahenta', 'left');
      //$this->db->join('pliki', 'wydatki.skan_id = pliki.id', 'left');
      $this->db->where('przychody_platnosci.id_platnosci', $id);
      $this->db->from("przychody_platnosci");
      $query = $this->db->get();

      return $query->row();
     */

    public function podglad_przychodu($id)
    {
        $this->db->select("*,datediff(`przychody`.`termin_platnosci`,NOW()) as ddif,datediff(`przychody`.`termin_platnosci`,przychody_platnosci.oplacono) as pdif,rejony.nazwa as rejont,"
            . "kontrahenci.nazwa as kontrah,`przychody`.`termin_platnosci` as termin,przychody_platnosci.oplacono as oplacono,");
        $this->db->join('przychody_platnosci', 'przychody.id_przychodu = przychody_platnosci.fk_przychodu');
        $this->db->join('rejony', 'przychody.id_rejonu = rejony.id_rejonu', 'left');
        $this->db->join('kontrahenci', 'przychody.fk_kontrahent = kontrahenci.id_kontrahenta', 'left');

        $this->db->where('przychody.id_przychodu', $id);
        $this->db->from("przychody");
        $query = $this->db->get();

        return $query->row();
    }

    public function pobierz_historie($param)
    {
        $this->db->where('fk_wydatku', $param);
        $this->db->where('typ', 2);
        $this->db->from("historia_zmian");
        $this->db->order_by("dokonano", "asc");
        $query = $this->db->get();

        return $query->result();
    }

    public function pobierz_platnosci($id)
    {
        $this->db->select("przychody_platnosci_historia.wplacono as datawplaty,kwota_wplacona,CONCAT(users.first_name,' ',users.last_name) as wplacilu");
        $this->db->join('users', 'przychody_platnosci_historia.wplacil = users.id');
        $this->db->where('przychody_platnosci_historia.dot_platnosci', $id);
        $this->db->from("przychody_platnosci_historia");
        $this->db->order_by("datawplaty", "asc");
        $query = $this->db->get();

        return $query->result();
    }

    public function pobierz_korekty($id)
    {
        $this->db->select("id as nrkorekty,nazwa");
        $this->db->where('fk_link', $id);
        $this->db->from("korekty_dokument");

        $query = $this->db->get();

        return $query->result_array();
    }


    function get_przychody($id_przychodu)
    {
        return $this->db->get_where('przychody', array('id_przychodu' => $id_przychodu))->row_array();
    }

    public function custom_decimal($decimal)
    {
        $decimal = str_replace("Zł ", "", $decimal);
        $decimal = str_replace(",", "", $decimal);

        if (preg_match('/^[0-9]+\.[0-9]{2}$/', $decimal))
            return $decimal;
        else
            return FALSE;
    }

    /*
     * POST
      inputData:2017-07-27
      inputDokument:FS/11
      inputBrutto:0.01
      inputMiasto:1
      inputRejon:2
      inputKontrahent:1
      inputOpis:uk
     * 
     * CREATE TABLE `przychody` (
      `id_przychodu` bigint(20) NOT NULL,
      `id_rejonu` bigint(20) NOT NULL,
      `fk_kontrahent` bigint(20) NOT NULL,
      `dodal` bigint(20) NOT NULL,
      `wartosc` decimal(10,2) NOT NULL,
      `netto` decimal(10,2) NOT NULL,
      `numer` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
      `z_dnia` date NOT NULL,
      `dodano` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `uwagi` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
      `dokument` bigint(20) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
     */

    function rangeMonth($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('Y-m-d', strtotime('first day of this month', $dt));
        $res['end'] = date('Y-m-d', strtotime('last day of this month', $dt));
        return $res;
    }

    public function decode_json_f_nr($j)
    {
        return json_decode($j)->response->message;
    }

    public function get_custom_k_nr($ff = FALSE)
    {

        $message = "";
        if ($ff) {
            $month = date("n", strtotime($ff));
            $year = date("y", strtotime($ff));
        } else {
            $month = date("n", strtotime($this->input->post("d")));
            $year = date("y", strtotime($this->input->post("d")));
        }


        if (date("n") === $month && date("y") === $year) {
            $fn = (int) $this->id_do_korekty();
            $message = $fn . "/KOM/" . sprintf('%02d', $month) . '/' . $year;
        } else {

            $starts = 1;
            $ends = date('t', strtotime($month . '/' . $year)); //Returns days in month 6/2011
            $this->db->select("count(id) as Tmonth");
            $this->db->from("korekty_dokument");
            $this->db->where('dodano >= ', $year . '-' . $month . '-' . $starts);
            $this->db->where('dodano <= ', $year . '-' . $month . '-' . $ends);

            $query = $this->db->get();

            $nr = $query->result();
            $message = bcadd((int) $nr[0]->Tmonth, 1) . "/KOM/" . sprintf('%02d', $month) . '/' . $year;
        }

        return json_encode(array("response" => array("status" => $year, "message" => $message)));
    }

    public function get_custom_f_nr($ff = FALSE)
    {

        $message = "";
        if ($ff) {
            $month = date("n", strtotime($ff));
            $year = date("y", strtotime($ff));
        } else {
            $month = date("n", strtotime($this->input->post("d")));
            $year = date("y", strtotime($this->input->post("d")));
        }


        if (date("n") === $month && date("y") === $year) {
            $fn = (int) $this->id_do_faktury();
            $message = $fn . "/KOM/" . sprintf('%02d', $month) . '/' . $year;
        } else {

            $starts = 1;
            $ends = date('t', strtotime($month . '/' . $year)); //Returns days in month 6/2011
            $this->db->select("count(id_przychodu) as Tmonth");
            $this->db->from("przychody");
            $this->db->where('przychody.z_dnia >= ', $year . '-' . $month . '-' . $starts);
            $this->db->where('przychody.z_dnia <= ', $year . '-' . $month . '-' . $ends);

            $query = $this->db->get();

            $nr = $query->result();
            $message = bcadd((int)$nr[0]->Tmonth, 1) . "/KOM/" . sprintf('%02d', $month) . '/' . $year;
        }

        return json_encode(array("response" => array("status" => $year, "message" => $message)));
    }

    public function id_do_korekty()
    {
        $range = $this->rangeMonth($this->input->post("termin"));
        $this->db->select("count(id) as Tmonth");
        $this->db->from("korekty_dokument");
        $this->db->where('dodano >= ', $range['start']);
        $this->db->where('dodano <= ', $range['end']);

        $query = $this->db->get();

        $nr = $query->result();
        return (int) bcadd($nr[0]->Tmonth, 1,0);
    }


    public function id_do_faktury()
    {
        $range = $this->rangeMonth(date('Y-m-d'));
        $this->db->select("count(id_przychodu) as Tmonth");
        $this->db->from("przychody");
        $this->db->where('przychody.z_dnia >= ', $range['start']);
        $this->db->where('przychody.z_dnia <= ', $range['end']);

        $query = $this->db->get();

        $nr = $query->result();
        return (int) bcadd($nr[0]->Tmonth, 1,0);
    }

    public function przedmioty_faktury($f)
    {
        $this->db->select("przychody.termin_platnosci,przychody.ilosc_dni,przychody.netto as s_netto,przychody.wartosc as s_brutto,id_przychodu,id_rejonu,fk_kontrahent,dodal,numer,z_dnia,dodano,uwagi,vat_lacznie,sprzedano,przychody_wpisy.*");
        $this->db->join('przychody', 'przychody_wpisy.do_przychodu = przychody.id_przychodu');

        $this->db->where('przychody_wpisy`.`do_przychodu', $f);
        $this->db->from("przychody_wpisy");
        $query = $this->db->get();

        return $query->result();
    }

    public function dodaj_przychod()
    {

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = 0;


        $this->load->helper(array('form', 'url'));

        $this->load->library('form_validation');

        /*
         * "{"inputDatawystaw":"2017-08-09",
         * "inputDatasprze":"2017-08-09",
         * "inputKontrahent":"1",
         * "inputRejon":"2",
         * "p_nazwa":["","Produkt"],
         * "p_ilosc":["","1"],
         * "p_cnetto":["","123"],
         * "p_pvat":["","22"],
         * "inputOpis":""}"
         */

        $this->form_validation->set_rules('inputBrutto2[]', 'rejon', 'decimal_check');
        $this->form_validation->set_rules('inputRejon2', 'rejon', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać rejon.',
            'alpha_numeric' => "Rejon może być tylko liczbą"
        ));

        $this->form_validation->set_rules('inputKontrahent2', 'kontrahent', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać kontrahenta.',
            'alpha_numeric' => "Wartość pola Kontrahent może być tylko liczbą"
        ));


        $this->form_validation->set_rules('inputOpis', 'opis', 'trim|min_length[3]|max_length[250]', array(
            'min_length' => "Opis musi mieć conajmniej 3 znaki",
            'max_length' => "Opis może mieć najwyżej 250 znaków",
        ));

        $brutto = $this->custom_decimal($this->input->post('inputBrutto'));
        $netto = $this->custom_decimal($this->input->post('inputNetto'));

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputDatawystaw'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputDatasprze'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }

        if (empty($this->input->post('termin_platnosci'))) {
            $message = "Termin płatności nie jest datą";
        }


        // usuwanie _POST[0] z div.template

        $nazwy = $this->input->post("p_nazwa");
        $ilosc = $this->input->post("p_ilosc");
        $wnetto = $this->input->post("p_cnetto");
        $wvatp = $this->input->post("p_pvat");

        $p_unit = $this->input->post("p_unit");
        $inc = $this->input->post("inc");

        $err_c = 0;
        if ($inc == 1) {
            $pracownicy = $this->input->post("inputPracownik");
            $udzialy = $this->input->post("p_procent");

            foreach ($pracownicy as $index => $v) {
                if (!is_numeric($v)) {
                    $err_c = 1;
                }
                if (is_numeric($udzialy[$index]) && floor($udzialy[$index]) != $udzialy[$index]) {
                    $err_c = 1;
                }
            }

        }


        // array_pop($wnetto);
        $tablica = count($wnetto);

        $batch_insertIDS = array();

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            if (empty($nazwy)) {
                $message = "Wprowadź conajmniej jedną pozycję";
            }
            if ($err_c == 1) {
                $message = "Nieprawidłowe wartości pracownika";
            }
            if (strlen($message) == 0) {
                $lacznie_brutto = 0;
                $lacznie_vat = 0;
                $lacznie_netto = 0;
                foreach ($wnetto as $index => $v) {

                    $sw = $this->custom_decimal($wnetto[$index]);
                    $dq = $this->custom_decimal($ilosc[$index]);

                    if (empty($nazwy[$index])) {
                        $message = "Proszę podać nazwę";
                        break;
                    }
                    if (strlen($nazwy[$index]) > 200) {
                        $message = "Nazwa może się składać max z 200 znaków";
                        break;
                    }
                    if (!$sw) {
                        $message = "Wartość netto nie jest liczbą";
                        break;
                    }
                    if (($wvatp[$index] < 0 || $wvatp[$index] > 99) || empty($wvatp[$index])) {
                        $message = "Procent vat nie jest liczbą ";
                        break;
                    }
                    if ($dq == FALSE) {
                        $message = "Ilość nie jest liczbą";
                        break;
                    }
                    if (empty($p_unit[$index])) {
                        $message = "Brak jednostki";
                        break;
                    }
                }
                // [{"netto":"11.00","vat":"22","nazwa":"xa","ilosc":"1.00","jednostka":"m2"}]
                try {
                    $this->db->trans_begin();
                    foreach ($wnetto as $index => $v) {

                        if (!is_numeric($wvatp[$index])) {
                            $net = $this->custom_decimal($wnetto[$index]);
                            $wvat = 0;
                            $bbrutto = $net;
                        } else {
                            $net = $this->custom_decimal($wnetto[$index]);
                            $wvat = ($net * $wvatp[$index]) / 100;
                            $bbrutto = $wvat + $net;
                        }

                        $post_data = array(
                            'nazwa' => $nazwy[$index],
                            'ilosc' => $ilosc[$index],
                            'netto' => $net,
                            'brutto' => $bbrutto,
                            'wartosc_vat' => $wvat,
                            'jednostka' => $p_unit[$index],
                        );
                        if (!is_numeric($wvatp[$index])) {
                            $post_data['typ_vat'] = $wvatp[$index];
                            $post_data['vat'] = 0;
                        } else {
                            $post_data['vat'] = $wvatp[$index];
                        }
                        $this->db->insert('przychody_wpisy', $post_data);

                        $batch_insertIDS[] = $this->db->insert_id();
                        $lacznie_brutto += bcmul($bbrutto, $ilosc[$index], 2);
                        $lacznie_netto += bcmul($net, $ilosc[$index], 2);
                        $lacznie_vat += bcmul($wvat, $ilosc[$index], 2);
                    }

                    if ($tablica != count($batch_insertIDS)) {
                        $this->db->trans_rollback();
                    }
                    // Dodawanie przychodu
                    if (is_numeric($this->input->post('termin_platnosci'))) {
                        $add = $this->input->post('termin_platnosci');
                        $termin_platnosci = date('Y-m-d', strtotime("+" . $add . " days"));
                        // inne
                        ///////
                    }

                    $post_data = array(
                        'id_rejonu' => $this->input->post('inputRejon2'),
                        'fk_kontrahent' => $this->input->post('inputKontrahent2'),
                        'dodal' => $this->ion_auth->user()->row()->id,
                        'z_dnia' => $this->input->post('inputDatawystaw'),
                        'sprzedano' => $this->input->post('inputDatasprze'),
                        'wartosc' => $lacznie_brutto,
                        'netto' => $lacznie_netto,
                        'vat_lacznie' => $lacznie_vat,
                        'numer' => $this->input->post('inputDokument'),
                        'uwagi' => $this->input->post('inputOpis'),
                        'numer' => $this->decode_json_f_nr($this->get_custom_f_nr($this->input->post('inputDatawystaw'))), //$this->id_do_faktury() . "/KOM/" . date('m/y'),
                        'termin_platnosci' => $termin_platnosci,
                        'ilosc_dni' => $this->input->post("termin_platnosci")
                    );

                    $this->db->insert('przychody', $post_data);
                    $status = 1;
                    $id = $this->db->insert_id();

                    if ($inc == 1) {
                        foreach ($pracownicy as $index => $v) {
                            $post_data = array(
                                'fk_pracownik' => $v,
                                'fk_przychodu' => $id,
                                'udzial' => $udzialy[$index],
                            );
                            $this->db->insert('przychody_udzialy', $post_data);
                        }
                    }


                    $updateArray = array();

                    for ($x = 0; $x < sizeof($batch_insertIDS); $x++) {

                        $updateArray[] = array(
                            'id_item' => $batch_insertIDS[$x],
                            'do_przychodu' => $id,
                        );
                    }
                    $this->db->update_batch('przychody_wpisy', $updateArray, 'id_item');


                    $pps = array(
                        'status	' => Przychody_model::do_zaplaty,
                        'pozostala_kwota' => $lacznie_brutto,
                        'fk_przychodu' => $id,
                    );
                    $this->db->insert('przychody_platnosci', $pps);


                    if ($this->db->trans_status() === FALSE || strlen($message) > 0 || $tablica != count($batch_insertIDS)) {
                        $this->db->trans_rollback();
                    } else {
                        $this->db->trans_commit();
                        if (is_numeric($id)) {
                            $message = "Dodano";
                        }
                    }
                } catch (Exception $e) {
                    $this->db->trans_rollback();
                    log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
                }
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

}
