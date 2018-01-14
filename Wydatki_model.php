<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * @author Kavvson
 */
class Wydatki_model extends CI_Model
{

    private $miesiac = array('Jan' => 'Styczn',
        'Feb' => 'Luty',
        'Mar' => 'Marzec',
        'Apr' => 'Kwiecien',
        'May' => 'Maj',
        'Jun' => 'Czerwiec',
        'Jul' => 'Lipiec',
        'Aug' => 'Sierpien',
        'Sep' => 'Wrzesien',
        'Oct' => 'Pazdziernik',
        'Nov' => 'Listopad',
        'Dec' => 'Grudzien');

    public function __construct()
    {
        parent::__construct();
    }

    public static function metoda_ikona($metoda)
    {
        $i = "";
        switch ($metoda) {
            case 1 :
                $i = '<img src="' . base_url('assets/images/zloty.png') . '"  alt="gotówka">';
                break;
            case 2:
                $i = '<img src="' . base_url('assets/images/transfer-money.png') . '"  alt="przelew">';
                break;
            case 3:
                $i = '<img src="' . base_url('assets/images/credit-card.png') . '"  alt="karta">';
                break;
            case 4 :
                $i = '<img src="' . base_url('assets/images/refund.png') . '"  alt="zwrot">';
                break;
        }

        return $i;
    }

    public function pobierz_historie($param)
    {
        $this->db->where('fk_wydatku', $param);
        $this->db->where('typ', 1);
        $this->db->from("historia_zmian");
        $this->db->order_by("dokonano", "asc");
        $query = $this->db->get();

        return $query->result();
    }

    protected function isLocked($w)
    {

        try {
            $this->db->trans_begin();
            $this->db->where('platnosci.fk_wydatek', $w);
            $this->db->from("platnosci");
            $query = $this->db->get();

            $wartosci = $query->row();


            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }
        return ($wartosci->status == 2) ? TRUE : FALSE;
    }

    function search_ar($products, $field, $value)
    {
        foreach ($products as $key => $product) {
            if ($product[$field] === $value)
                return $key;
        }
        return false;
    }

    public function modyfikacja_wydatku($param)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = 0;
        $fid = NULL;
        $podkategoria_paliwo = FALSE;
        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');
        $wpisy_wydatku = $this->input->post('data[InvoiceContent]');
        // jezeli wybrano kategorie 4 = paliwo, sprawdz czy podpieto auto

        $this->form_validation->set_rules('inputRejon', 'rejon', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać rejon.',
            'exact_length' => "Rejon musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Rejon może być tylko liczbą"
        ));


        $this->form_validation->set_rules('inputKontrahent', 'kontrahent', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać kontrahenta.',
            'alpha_numeric' => "Wartość pola Kontrahent może być tylko liczbą"
        ));
        $this->form_validation->set_rules('inputDokument', 'numer dokumentu', 'required|min_length[3]|max_length[100]', array(
            'required' => 'Musisz podać numer dokumentu.',
            'min_length' => "Numer dokumentu musi mieć conajmniej 3 znaki",
            'max_length' => "Numer dokumentu może mieć najwyżej 100 znaków",
        ));
        $this->form_validation->set_rules('inputOpis', 'opis', 'trim|min_length[3]|max_length[100]', array(
            'min_length' => "Opis musi mieć conajmniej 3 znaki",
            'max_length' => "Numer dokumentu może mieć najwyżej 250 znaków",
        ));

        $this->form_validation->set_rules('inputMetoda', 'Metoda płatności', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać metode płatności.',
            'exact_length' => "Metoda płatności musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola Metoda płatności może być tylko liczbą"
        ));

        $this->form_validation->set_rules('inputPriorytet', 'Priorytet płatności', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać %s.',
            'exact_length' => "%s musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola %s może być tylko liczbą"
        ));


        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputData'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputTermin'))) {
            $message = "Nieprawidłowy format daty terminu płatności rrrr-mm-dd";
        }

        $ilosclitrow = $this->custom_decimal($this->input->post('inputLitry'));

        if (count(array_keys(array_column($wpisy_wydatku, 'name'), 4)) == 1) {
            $pojazd = $this->input->post("inputPojazd");
            $p = $this->load->model("Pojazdy_model", "p");
            if ($pojazd) {
                $istniejewbazie = $this->p->get_vehicle("exists", $pojazd);
                if ($istniejewbazie["total"] == 0) {
                    $message = "Pojazd nie znajduje się w bazie danych";
                }
                if (!$ilosclitrow || $ilosclitrow === "0.00") {

                    $message = "Proszę podać ilość litrów";
                }
            } else if (!$this->input->post("wydatek_auto_id")) {
                $message = "Proszę wybrać pojazd";
            }
        } else if (count(array_keys(array_column($wpisy_wydatku, 'name'), 4)) > 1) {
            $message = "Nie może być kilku kategorii Paliwo";
        }

        $sprawdz_duplikaty = $this->sprawdz_duplikat_edycja($wpisy_wydatku);
        if (!empty($sprawdz_duplikaty)) {
            // brak duplikatow
            $message = "Kategorie nie mogą się potwarzać. Liczba powtórzeń - " . $sprawdz_duplikaty;
        }

        $locked = $this->isLocked($param);
        if (!empty($locked)) {
            // $message = "Nie można edytować już opłaconych wydatków";
        }

        // Sprawdzenie płatności
        // validacja

        foreach ($wpisy_wydatku as $g => $b) {

            if (empty($b['name']) || strlen($b['name']) < 1) {
                $message = "Wybierz kategorię";
            }
            if (empty($this->custom_decimal($b['netto'])) || strlen($this->custom_decimal($b['netto'])) < 1 || $this->custom_decimal($b['netto']) === "0.00") {
                $message = "Podaj kwotę netto";
            }
            if (empty($b['vat']) || strlen($b['vat']) < 1 || strlen($b['vat'] > 99)) {
                $message = "Podaj vat";
            }
        }


        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            if (empty($wpisy_wydatku)) {
                $message = "Wprowadź conajmniej jedną pozycję";
            }


            $lacznie_brutto = 0;
            $lacznie_vat = 0;
            $lacznie_netto = 0;
            $maxKat = array();
            $isRozbita = FALSE;
            if (count($wpisy_wydatku) > 1) {
                $isRozbita = TRUE;
            }
            try {
                $this->db->trans_begin();
                $id_paliwo = FALSE;
                foreach ($wpisy_wydatku as $index => $v) {

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
                        'kategoria' => $v["name"],
                        'netto' => $net,
                        'brutto' => $bbrutto,
                        'wartosc_vat' => $wvat,
                    );
                    if (!is_numeric($v['vat'])) {
                        $post_data['typ_vat'] = $v["vat"];
                        $post_data['vat'] = 0;
                    } else {
                        $post_data['vat'] = $v["vat"];
                    }
                    if (strlen($index) > 11) {
                        $post_data['do_wydatku'] = $param;
                        $nk = $this->db->insert('wydatki_wpisy', $post_data);
                        $maxKat[$nk] = $bbrutto;
                    } else {
                        $this->db->where('id_item', $index);
                        $this->db->update('wydatki_wpisy', $post_data);
                        $maxKat[$index] = $bbrutto;
                    }

                    if ($v["name"] == 4) {
                        if (strlen($index) > 11) {
                            $id_paliwo = $nk;
                        } else {
                            $id_paliwo = $index;
                        }

                        $podkategoria_paliwo = $id_paliwo;
                        //var_dump($v);
                    }

                    $lacznie_brutto += $bbrutto;
                    $lacznie_netto += $net;
                    $lacznie_vat += $wvat;
                }


                /*
                 * Validacja skanu
                 * Dodawanie skanu
                 */

                $this->load->model("File_handler", "pliki");
                $this->pliki->fext("jpg|jpeg|pdf|png");
                $hook = $this->pliki->upload_file("inputSkan", "/wydatki/" . date('Y') . "/" . $this->miesiac[date('M')] . "/" . $this->input->post('inputKontrahent'));

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

                $maxprice = max($maxKat);

                $post_data = array(
                    'id_rejonu' => $this->input->post('inputRejon'),
                    'kontrahent' => $this->input->post('inputKontrahent'),
                    'id_kupujacy' => $this->input->post('inputKupiec'),
                    'dodal' => $this->ion_auth->user()->row()->id,
                    'data_zakupu' => $this->input->post('inputData'),
                    'kwota_brutto' => $lacznie_brutto,
                    'kwota_netto' => $lacznie_netto,
                    'dokument' => $this->input->post('inputDokument'),
                    'cel_zakupu' => $this->input->post('inputOpis'),
                    'wartosc_vat' => $lacznie_vat,
                    'procent_vat' => $this->input->post('data[InvoiceContent][' . array_search($maxprice, $maxKat) . '][vat]'),

                    'metoda_platnosci' => $this->input->post('inputMetoda'),
                    'kategoria' => $this->input->post('data[InvoiceContent][' . array_search($maxprice, $maxKat) . '][name]'),
                    'fk_narzecz' => $this->input->post('inputNaRzecz'),
                    'fk_kontrakt' => $this->input->post('inputKontrakt'),
                );
                if (isset($fid) && !empty($fid)) {
                    $post_data["skan_id"] = $fid;
                }
                $this->db->where("id_wydatku", $param);
                $this->db->update("wydatki", $post_data);


                $platnosci = array(
                    'utworzenie_platnosci' => $this->input->post('inputData'),
                    'termin_platnosci' => $this->input->post('inputTermin'),
                    'priorytet' => $this->input->post('inputPriorytet'),
                    'fk_rozbita' => $isRozbita
                );
                $this->db->where('fk_wydatek', $param);
                $this->db->update('platnosci', $platnosci);

                if (!empty($podkategoria_paliwo)) {
                    if ($this->input->post("wydatek_auto_id")) {
                        $pwydp = array(
                            'fk_wydatku' => $podkategoria_paliwo,
                            'fk_pojazd' => $this->input->post('inputPojazd'),
                            'litry' => $ilosclitrow
                        );
                        $this->db->where('id_wydatku', $this->input->post("wydatek_auto_id"));
                        $this->db->update('pojazdy_wydatki', $pwydp);
                    } else if ($pojazd) {
                        $pwydp = array(
                            'fk_wydatku' => $podkategoria_paliwo,
                            'fk_pojazd' => $this->input->post('inputPojazd'),
                            'litry' => $ilosclitrow
                        );
                        $this->db->insert('pojazdy_wydatki', $pwydp);
                    }
                }

                if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                    $message = "Zmodyfikowano";
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

    public function pobierz_fakture_auto_edycja($fk_wydatek)
    {
        $this->db->select("nr_rej,poj_id,litry,id_wydatku");

        $this->db->join('pojazdy_wydatki', 'wydatki_wpisy.id_item = `pojazdy_wydatki`.`fk_wydatku`');
        $this->db->join('pojazdy', 'pojazdy_wydatki.fk_pojazd = pojazdy.poj_id');
        $this->db->where('wydatki_wpisy.do_wydatku', $fk_wydatek);
        $this->db->from("wydatki_wpisy");
        $query = $this->db->get();

        return $query->row();
    }

    // pobiera numer auta zwiazany z faktura
    // zwraca tylko 1 wiersz bo nie moze byc wicej aut na 1 fakture ??{}??
    // return nr_rej
    public function pobierz_fakture_auto($fk_wydatek)
    {
        $this->db->select("nr_rej,poj_id,litry,id_wydatku");
        $this->db->join('pojazdy', 'pojazdy_wydatki.fk_pojazd = pojazdy.poj_id');
        $this->db->where('fk_wydatku', $fk_wydatek);
        $this->db->from("pojazdy_wydatki");
        $query = $this->db->get();

        return $query->row();
    }

    // @operacja : Przychod / Wydatek

    public function rozlicz_wydatki($operacja)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }


        $message = "";
        $status = FALSE;
        $data_oplacenia = null;
        $json = json_decode($this->input->post("j"));
        $getIds = array();

        foreach ($json as $j) {
            if (!is_numeric($j->id)) {
                $message = "Nie przekazano parametru";
            }
            $getIds[] = $j->id;
        }

        if ($operacja === "Przychod") {
            $this->db->select("przychody_platnosci.*,przychody.numer");
            $tb = "przychody_platnosci";
            $nazwa_kwoty = "otrzymana_kwota";
            $fk = "fk_przychodu";

            $this->db->join('przychody', 'przychody.id_przychodu = przychody_platnosci`.`fk_przychodu', 'left');
        } else {
            ////////////////////////////////
            // die("Not implemented");
            $this->db->select("platnosci.*,wydatki.dokument as numer");
            ////////////////////////////////
            $tb = "platnosci";
            $nazwa_kwoty = "zaplacona_kwota";
            $fk = "fk_wydatek";
            $this->db->join('wydatki', 'wydatki.id_wydatku = platnosci`.`fk_wydatek', 'left');
        }


        $this->db->where_in($fk, $getIds);
        $this->db->from($tb);
        $query = $this->db->get()->result_array();

        $res = array();
        foreach ($query as $re) {
            $org_t = $re[$nazwa_kwoty] + $re['pozostala_kwota'];
            $res[$re[$fk]] = array(
                'id' => $re['id_platnosci'],
                'paid' => $re[$nazwa_kwoty],
                'due' => $re['pozostala_kwota'],
                'paiddata' => $re['oplacono'],
                'status' => $re['status'],
                'numer' => $re['numer'],
                'org' => $org_t
            );
        }


        // Compare
        foreach ($json as $z) {
            if (empty($res[$z->id])) {
                $message = "Nie odnaleziono płatności";
            }

        }


        /* new

*/

        if ($operacja === "Przychod") {
            $userBudget = $this->input->post("target_price");
            if ($userBudget == 0.00 || $userBudget < 0) {
                $message = "Nieprawidłowa wartość przelewu";
            }
        }

        try {
            $this->db->trans_begin();

            if ($operacja === "Przychod") {
                $invoiceUpdate = array_map(function ($item) use (&$userBudget) {
                    $newItem = ['id' => $item['id']];
                    $amount = $userBudget > $item['due'] ? $item['due'] : $userBudget;
                    $userBudget -= $amount;
                    $statusl = $item['due'] - $amount;
                    if ($statusl == 0) {
                        $status = 1;
                        $data = date("Y-m-d");
                    } else {
                        $status = 2;
                        $data = null;
                    }


                    return [
                        'id' => $item['id'],
                        'newDue' => $item['due'] - $amount,
                        'newPaid' => $item['paid'] + $amount,
                        'oplacono' => $data,
                        'status' => $status,
                        'org' => $item['org'],
                        'numer' => $item['numer']
                    ];
                }, $res);


                $batch_id = array();
                foreach ($invoiceUpdate as $upd) {
                    $post_data = array(
                        'oplacono' => $upd['oplacono'],
                        'pozostala_kwota' => $upd['newDue'],
                        $nazwa_kwoty => $upd['newPaid'],
                        'status' => $upd['status']
                    );
                    $this->db->where($fk, $upd['id']);
                    $this->db->update($tb, $post_data);
                    $batch_id[] = $this->db->insert_id();
                }


                $request = count($json);

            } else {
                $result_array = array();
                foreach ($json as $c) {

                    $kwota_czesciowalubcala_do_zaplaty = $c->price;


                    $pozostala_kwota_do_zaplaty = $res[$c->id]["due"];
                    $zaplacona_kwota_org = $res[$c->id]["paid"];

                    // debug

                    if ($pozostala_kwota_do_zaplaty > 0) {
                        // jest jeszcze kwota do zaplaty - ile ?
                        // kwota ktora chcemy zaplacic jest wieksza od pozostalej do zaplaty

                        if ($kwota_czesciowalubcala_do_zaplaty > $pozostala_kwota_do_zaplaty) {
                            $message = "Podana kwota jest większa od kwoty pozostałej do zapłaty";
                        } else {
                            $nowa_pozostala_kwota = bcsub($pozostala_kwota_do_zaplaty, $kwota_czesciowalubcala_do_zaplaty, 2);
                            $nowa_zaplacona_kwota = bcadd($zaplacona_kwota_org, $kwota_czesciowalubcala_do_zaplaty, 2); // wartość całego wydatku

                            if ($nowa_pozostala_kwota > 0) {
                                $status = 1;
                            } else {
                                $status = 2;
                                $data_oplacenia = date('Y-m-d');
                            }
                        }
                    } else {
                        $message = "Wydatek jest już opłacony";
                    }


                    $post_data = array(
                        $nazwa_kwoty => $nowa_zaplacona_kwota,
                        'pozostala_kwota' => $nowa_pozostala_kwota,
                    );
                    if (!empty($data_oplacenia)) {
                        $post_data['oplacono'] = $data_oplacenia;
                    }
                    $post_data['status'] = $status;

                    $this->db->where($fk, $c->id);
                    $this->db->update($tb, $post_data);
                    $batch_id[] = $this->db->insert_id();
                    $wartosc_faktury = bcadd($res[$c->id]["paid"], $res[$c->id]["due"], 2);
                    $result_array[] = array(
                        $nazwa_kwoty => $nowa_zaplacona_kwota,
                        'pozostala_kwota' => $nowa_pozostala_kwota,
                        'numer' => $res[$c->id]["numer"],
                        'wartosc' => $wartosc_faktury
                    );

                }

                $request = count($json);

            }


            if ($this->db->trans_status() === FALSE || strlen($message) > 0 || $request != count($batch_id)) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
                $message = "Rozliczono";
                $status = 1;
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }
        // $pdfFilePath = '';
        if ($status == 1) {
            $this->load->model("Generatorpdf_model", "p");
            $this->load->helpers("Helpers");
            if ($operacja === "Przychod") {
                $data = array(
                    'kwota_przelewu' => $this->input->post("target_price"),
                    'dane' => $invoiceUpdate
                );
                $np = date("Y-m-d_h-i-s");
                $html = $this->load->view('pdfy/potwierdzenie_oplacenia', $data, true);
                $pdfFilePath = "files/koszyk/przychody/PotwierdzenieP-" . $np . "-" . $request . ".pdf";
            } else {
                $data = array(
                    'kwota_przelewu' => $wartosc_faktury,
                    'dane' => $result_array
                );
                $np = date("Y-m-d_h-i-s");
                $html = $this->load->view('pdfy/potwierdzenie_wydatku', $data, true);
                $pdfFilePath = "files/koszyk/wydatki/PotwierdzenieW-" . $np . "-" . $request . ".pdf";
            }

            //load mPDF library
            $this->load->library('M_pdf');


            $this->m_pdf->pdf->SetDisplayMode('fullpage');

            $this->m_pdf->pdf->list_indent_first_level = 0; // 1 or 0 - whether to indent the first level of a list
            //generate the PDF from the given html
            $this->m_pdf->pdf->WriteHTML($html);

            //download it.
            $this->m_pdf->pdf->Output($pdfFilePath, "F");

        }
        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("response" => array("status" => $status, "message" => $message, 'potwierdzenie' => $pdfFilePath))));


    }


    public function oplacWydatek($dp)
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

                $this->db->where('platnosci.id_platnosci', $dp);
                $this->db->from("platnosci");
                $query = $this->db->get();

                $wartosci = $query->row();

                $zaplacona_kwota = $wartosci->zaplacona_kwota;
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

                    $this->db->insert('platnosci_historia', $post_data);
                    $idw = $this->db->insert_id();


                    $this->db->where('id_platnosci', $dp);
                    $this->db->set('status', $statusi);
                    if ($czyOplacona == 0) {

                        $this->db->set('oplacono', date('Y-m-d'));
                    }

                    $this->db->set('zaplacona_kwota', 'zaplacona_kwota+' . $brutto, FALSE);
                    $this->db->set('pozostala_kwota', 'pozostala_kwota-' . $brutto, FALSE);
                    $this->db->update('platnosci');
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

    public function pobierz_faktury_powiazane($do_glownej, $raw = FALSE)
    {
        if (!$raw) {
            $this->db->select("wydatki_wpisy.*,wydatki_kategorie.nazwa as knazw");
            $this->db->join('wydatki', 'wydatki_wpisy.do_wydatku = wydatki.id_wydatku');
            $this->db->join('wydatki_kategorie', 'wydatki_wpisy.kategoria = wydatki_kategorie.id_kat', 'left');
        }
        $this->db->where('wydatki_wpisy.do_wydatku', $do_glownej);
        $this->db->from("wydatki_wpisy");
        $query = $this->db->get();

        return $query->result();
    }

    public static function odmiana($liczba, $pojedyncza, $mnoga, $mnoga_dopelniacz)
    {
        $liczba = abs($liczba); // tylko jeśli mogą zdarzyć się liczby ujemne
        if ($liczba === 1) {
            return $pojedyncza;
        } else {
            $reszta10 = $liczba % 10;
            $reszta100 = $liczba % 100;
            if ($reszta10 > 4 || $reszta10 < 2 || ($reszta100 < 15 && $reszta100 > 11)) {
                return $mnoga_dopelniacz;
            } else {
                return $mnoga;
            }
        }
    }

    public static function termin_to_icon($t, $kw)
    {
        if ($kw > 0) {
            if ($t == 0) {
                return '<i class="icon-exclamation icon text-yellow" style="font-size:2rem;"></i> Dzisiaj';
            }
            if ($t > 0) {
                return 'za ' . $t . ' ' . Wydatki_model::odmiana($t, 'dzień', 'dni', 'dni');
            }
            if ($t < 0) {
                $dd = str_replace("-", "", $t);
                return '<i class="icon-exclamation icon text-red" style="font-size:2rem;"></i> ' . $dd . '  ' . Wydatki_model::odmiana($t, 'dzień', 'dni', 'dni') . ' po';
            }
        } else {
            return $t;
        }
    }

    public static function priorytet_to_icon($p)
    {
        switch ($p) {
            case "3":
                return '<i class="icon icon-hexagon-outline s40"></i> Normalny';
                break;
            case "2":
                return '<i class="icon icon-arrow-up-bold-hexagon-outline text-orange s40"></i> Istotny';
                break;
            case "1":
                return '<i class="icon icon-alert-octagon text-red s40"></i> Pilny';
                break;
        }
    }


    public function wydatki_select($id)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }


        // $limit = $this->input->get('page_limit');

        $this->db->select('wydatki.kwota_brutto,wydatki.dokument,wydatki.id_wydatku as id,wydatki.metoda_platnosci');
        $this->db->from('wydatki');
        $this->db->join('wydatki_bank', 'wydatki_bank.fk_wydatku = wydatki.id_wydatku', 'left');
        $this->db->where('id_kupujacy', $id);
        $this->db->where('wydatki_bank.id_powiazania IS NULL', null, false);
        $this->db->like('dokument', $this->input->get("q"), 'after');
        $this->db->order_by('wydatki.kwota_brutto', 'DESC');
        if ((isset($_GET['customMonth']) && $_GET['customMonth'] >= 1 && $_GET['customMonth'] <= 12) &&
            (isset($_GET['customYear']) && $_GET['customYear'] >= 2017 && $_GET['customMonth'] <= 2050)) {

            $query_date = $_GET['customYear'] . '-' . $_GET['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('data_zakupu >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('data_zakupu <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        //$query = $this->db->limit($limit);
        $query = $this->db->get();

        $rowcount = $query->num_rows();

        //echo json_encode(array('total'=>$rowcount,'results' =>$query->result_array()));
        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("total_count" => $rowcount, "incomplete_results" => true, "items" => $query->result_array())));

    }

    public function kategorie_wydatku()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $data = array();
        $getAd = $this->input->get('term');
        $limit = $this->input->get('page_limit');
        $this->db->select('id_kat as id,nazwa as text,do_pojazdu')
            ->from('wydatki_kategorie')
            ->like('nazwa', $getAd);

        $query = $this->db->limit($limit);
        $query = $this->db->get();

        $rowcount = $query->num_rows();

        $result = $query->result_array();
        if (count($result) > 0) {
            foreach ($result as $key => $value) {
                $data[] = array('id' => $value['id'], 'text' => $value['text'], 'pojazd' => $value['do_pojazdu']);
            }
        }

        echo json_encode($data);
    }

    private function dokument_istnieje($key)
    {
        $this->db->where('dokument', $key);
        $query = $this->db->get('wydatki');
        if ($query->num_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    private function sprawdz_duplikat(array $a)
    {
        return array_diff_key($a, array_unique($a));
    }

    private function sprawdz_duplikat_edycja(array $a)
    {
        $rep = array();
        foreach ($a as $b) {

            $rep[] = $b['name'];
        }

        return count($rep) !== count(array_unique($rep));
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

    public function podglad_wydatku($id)
    {
        $this->db->select("*,wydatki_kategorie.nazwa as knazwa,datediff(`platnosci`.`termin_platnosci`,NOW()) as ddif,datediff(`platnosci`.`termin_platnosci`,platnosci.oplacono) as pdif,rejony.nazwa as rejont,
        CONCAT(pracownicy.imie,' ',pracownicy.nazwisko) as kupujacy,CONCAT(z.imie,' ',z.nazwisko) as zwroc_in,"
            . "kontrahenci.nazwa as kontrah,wydatki_kategorie.nazwa as kat,platnosci.fk_rozbita as rozbita,"
            . "`platnosci`.`termin_platnosci` as termin,`platnosci`.`priorytet` as priorytet,platnosci.oplacono as oplacono,pracownicy.konto as pkonto");
        $this->db->join('wydatki', 'platnosci.fk_wydatek = wydatki.id_wydatku');
        $this->db->join('rejony', 'wydatki.id_rejonu = rejony.id_rejonu', 'left');

        $this->db->join('pracownicy', 'wydatki.id_kupujacy = pracownicy.id_pracownika', 'left');
        $this->db->join('pracownicy z', 'platnosci.zwroc = z.id_pracownika', 'left');


        $this->db->join('kontrahenci', 'wydatki.kontrahent = kontrahenci.id_kontrahenta', 'left');
        $this->db->join('wydatki_kategorie', 'wydatki.kategoria = wydatki_kategorie.id_kat', 'left');
        $this->db->join('pliki', 'wydatki.skan_id = pliki.id', 'left');
        $this->db->where('wydatki.id_wydatku', $id);
        $this->db->from("platnosci");
        $query = $this->db->get();

        return $query->row();
    }

    public function pobierz_org_proforme($wyd_id)
    {
        $this->db->select("pliki.*");

        $this->db->join('pliki', 'wydatki.prof_skan = pliki.id', 'left');
        $this->db->where('wydatki.id_wydatku', $wyd_id);
        $this->db->from("wydatki");
        $query = $this->db->get();

        return $query->row();
    }

    public function zalacz_pro_fv()
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = 0;
        $fid = NULL;
        $this->load->helper(array('form', 'url'));


        $this->load->library('form_validation');

        if (empty($_FILES['inputSkan']['name'])) {
            $this->form_validation->set_rules('inputSkan', 'inputSkan', 'trim|required', array(
                'required' => 'Musisz dodać skan.',
            ));
        }

        $this->form_validation->set_rules('fk_wydatku', 'fk_wydatek', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać id wydatku.',
            'alpha_numeric' => "Nie odnaleziono wydatku"
        ));

        $fk_wydatek = $this->input->post("fk_wydatku");


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
                $hook = $this->pliki->upload_file("inputSkan", "/wydatki/" . date('Y') . "/" . $this->miesiac[date('M')] . "/" . $this->input->post('fk_wydatek'));

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

                $wydatek = array(
                    'prof_skan' => $fid
                );
                $this->db->where('id_wydatku', $fk_wydatek);
                $this->db->update('wydatki', $wydatek);


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

    public function dodaj_wydatek()
    {

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $message = "";
        $status = 0;
        $fid = NULL;
        $podkategoria_paliwo = FALSE;
        $this->load->helper(array('form', 'url'));


        $this->load->library('form_validation');


        $this->form_validation->set_rules('inputRejon', 'rejon', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać rejon.',
            'exact_length' => "Rejon musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Rejon może być tylko liczbą"
        ));

        $this->form_validation->set_rules('inputRodzaj', 'inputRodzaj', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać inputRodzaj.',
            'exact_length' => "inputRodzaj musi mieć dokładnie 1 znak",
            'alpha_numeric' => "inputRodzaj może być tylko liczbą"
        ));

        $this->form_validation->set_rules('inputNaRzecz', 'rejon', 'trim|alpha_numeric', array(
            'alpha_numeric' => "Na rzecz może być tylko liczbą"
        ));

        $this->form_validation->set_rules('inputKontrakt', 'rejon', 'trim|alpha_numeric', array(
            'alpha_numeric' => "Rejon może być tylko liczbą"
        ));

        $this->form_validation->set_rules('inputKategoria[]', 'kategoria', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać kategorię wydatku.',
            'alpha_numeric' => "Wartość pola Kategoria wydatku może być tylko liczbą"
        ));
        $this->form_validation->set_rules('p_pvat[]', 'kategoria', 'trim|required|min_length[1]|max_length[2]', array(
            'required' => 'Musisz podać Vat [%].',
            'min_length' => "Vat [%] może mieć nie mniej niż 1 znak",
            'max_length' => "Wartość pola Vat [%] może mieć 2 znaki"
        ));

        $this->form_validation->set_rules('inputKontrahent', 'kontrahent', 'trim|required|alpha_numeric', array(
            'required' => 'Musisz podać kontrahenta.',
            'alpha_numeric' => "Wartość pola Kontrahent może być tylko liczbą"
        ));
        $this->form_validation->set_rules('inputDokument', 'numer dokumentu', 'required|min_length[3]|max_length[100]', array(
            'required' => 'Musisz podać numer dokumentu.',
            'min_length' => "Numer dokumentu musi mieć conajmniej 3 znaki",
            'max_length' => "Numer dokumentu może mieć najwyżej 100 znaków",
        ));
        $this->form_validation->set_rules('inputOpis', 'opis', 'trim|min_length[3]|max_length[100]', array(
            'min_length' => "Opis musi mieć conajmniej 3 znaki",
            'max_length' => "Numer dokumentu może mieć najwyżej 250 znaków",
        ));

        $this->form_validation->set_rules('inputMetoda', 'Metoda płatności', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać metode płatności.',
            'exact_length' => "Metoda płatności musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola Metoda płatności może być tylko liczbą"
        ));
        $this->form_validation->set_rules('inputStatus', 'Status płatności', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać %s.',
            'exact_length' => "%s musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola %s może być tylko liczbą"
        ));
        $this->form_validation->set_rules('inputPriorytet', 'Priorytet płatności', 'trim|required|exact_length[1]|alpha_numeric', array(
            'required' => 'Musisz podać %s.',
            'exact_length' => "%s musi mieć dokładnie 1 znak",
            'alpha_numeric' => "Wartość pola %s może być tylko liczbą"
        ));


        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputData'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('inputTermin'))) {
            $message = "Nieprawidłowy format daty terminu płatności rrrr-mm-dd";
        }


        $ilosclitrow = $this->custom_decimal($this->input->post('inputLitry'));
        $inputStanLicznika = $this->input->post('inputStanLicznika');
        $stanlicznika_nieznany = $this->input->post('stanlicznika_nieznany');

        // jezeli wybrano kategorie 4 = paliwo, sprawdz czy podpieto auto
        // TODO :::
        if (in_array(4, $this->input->post('inputKategoria'))) {

            $cav = array_count_values($this->input->post('inputKategoria'));
            if ($cav[4] > 1) {
                $message = "Nie może być kilku kategorii Paliwo";
            } else {
                $pojazd = $this->input->post("inputPojazd");
                $p = $this->load->model("Pojazdy_model", "p");
                if ($pojazd) {
                    $istniejewbazie = $this->p->get_vehicle("exists", $pojazd);
                    if ($istniejewbazie["total"] == 0) {
                        $message = "Pojazd nie znajduje się w bazie danych";
                    }
                    if (!$stanlicznika_nieznany) {
                        if (!$inputStanLicznika || !is_numeric($inputStanLicznika)) {
                            $message = "Proszę podać stan licznika";
                        }
                    }

                    if (!$ilosclitrow || $ilosclitrow === "0.00") {

                        $message = "Proszę podać ilość litrów";
                    }
                } else {
                    $message = "Proszę wybrać pojazd";
                }
            }
        }


        if ($this->dokument_istnieje($this->input->post('inputDokument'))) {
            $message = "Podana faktura została już wprowadzona";
        }

        if (!empty($this->sprawdz_duplikat($this->input->post('inputKategoria')))) {
            // brak duplikatow
            $message = "Kategorie nie mogą się potwarzać. Liczba powtórzeń - " . count($this->sprawdz_duplikat($this->input->post('inputKategoria')));
        }

        $zwroc_osobie = $this->input->post("zwr_os_sel");

        if ($this->input->post("inputMetoda") === "4" && strlen($zwroc_osobie) === 0) {
            $message = "Proszę wybrać osobę do zwrotu środków";
        }


        // usuwanie _POST[0] z div.template

        $nazwy = $this->input->post("inputKategoria");
        $wnetto = $this->input->post("p_cnetto");
        $wvatp = $this->input->post("p_pvat");

        $inputRodzaj = $this->input->post("inputRodzaj");
        // array_pop($wnetto);
        $tablica = count($wnetto);

        $batch_insertIDS = array();

        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {
            if (empty($nazwy)) {
                $message = "Wprowadź conajmniej jedną pozycję";
            }
            $this->load->model("Wydatki_kategorie_model");
            $attach_car = FALSE;
            $target_kat = array();
            foreach ($wnetto as $index => $v) {

                $sw = $this->custom_decimal($wnetto[$index]);

                if (empty($nazwy[$index])) {
                    $message = "Proszę podać nazwę";
                    break;
                }

                $katcheck = $this->Wydatki_kategorie_model->get_wydatki_kategorie($nazwy[$index]);
                if ($katcheck['do_pojazdu'] === "1" && empty($this->input->post("inputPojazd"))) {
                    $message = "Proszę wybrać pojazd";
                    break;
                }

                if ($katcheck['do_pojazdu'] === "1") {
                    $attach_car = TRUE;
                    $target_kat[] = $nazwy[$index];
                }

                if (strlen($nazwy[$index]) > 200) {
                    $message = "Nazwa może się składać max z 200 znaków";
                    break;
                }
                if (!$sw || $sw === "0.00") {
                    $message = "Wartość netto nie jest liczbą";
                    break;
                }
                if (($wvatp[$index] < 0 || $wvatp[$index] > 99) || empty($wvatp[$index])) {
                    $message = "Procent vat nie jest liczbą";
                    break;
                }
            }

            $lacznie_brutto = 0;
            $lacznie_vat = 0;
            $lacznie_netto = 0;
            // [{"netto":"11.00","vat":"22","nazwa":"xa","ilosc":"1.00","jednostka":"m2"}]
            (count($wnetto) > 1) ? $isRozbita = TRUE : $isRozbita = FALSE;
            try {
                $this->db->trans_begin();
                /*
                 * Validacja skanu
                 * Dodawanie skanu
                 */

                $this->load->model("File_handler", "pliki");
                $this->pliki->fext("jpg|jpeg|pdf|png");
                $hook = $this->pliki->upload_file("inputSkan", "/wydatki/" . date('Y') . "/" . $this->miesiac[date('M')] . "/" . $this->input->post('inputKontrahent'));
                $maxKat = array();
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
                        'kategoria' => $nazwy[$index],
                        'netto' => $net,
                        'brutto' => $bbrutto,
                        'wartosc_vat' => $wvat,
                    );
                    if (!is_numeric($wvatp[$index])) {
                        $post_data['typ_vat'] = $wvatp[$index];
                        $post_data['vat'] = 0;
                    } else {
                        $post_data['vat'] = $wvatp[$index];
                    }
                    $this->db->insert('wydatki_wpisy', $post_data);
                    $batch_insertIDS[] = $this->db->insert_id();
                    $maxKat[$index] = $bbrutto;
                    $lacznie_brutto += $bbrutto;
                    $lacznie_netto += $net;
                    $lacznie_vat += $wvat;
                    if (in_array($nazwy[$index], $target_kat)) {
                        $cat_ins_ids[] = $this->db->insert_id();
                    }
                    if ($nazwy[$index] == 4) {

                        $podkategoria_paliwo = $this->db->insert_id();
                    }
                }

                ///////
                $maxprice = max($maxKat);
                $post_data = array(
                    'id_rejonu' => $this->input->post('inputRejon'),
                    'kontrahent' => $this->input->post('inputKontrahent'),
                    'id_kupujacy' => $this->input->post('inputKupiec'),
                    'dodal' => $this->ion_auth->user()->row()->id,
                    'data_zakupu' => $this->input->post('inputData'),
                    'kwota_brutto' => $lacznie_brutto,
                    'kwota_netto' => $lacznie_netto,
                    'dokument' => $this->input->post('inputDokument'),
                    'cel_zakupu' => $this->input->post('inputOpis'),
                    'wartosc_vat' => $lacznie_vat,
                    'procent_vat' => $this->input->post('p_pvat')[array_search($maxprice, $maxKat)],
                    'skan_id' => $fid,
                    'metoda_platnosci' => $this->input->post('inputMetoda'),
                    'kategoria' => $this->input->post('inputKategoria')[array_search($maxprice, $maxKat)],

                );
                if ($inputRodzaj == 1) {
                    $post_data['pro_forma'] = 1;
                } else {
                    $post_data['pro_forma'] = null;
                }
                (!empty($this->input->post("inputKontrakt"))) ? $post_data['fk_kontrakt'] = $this->input->post("inputKontrakt") : null;
                (!empty($this->input->post("inputNaRzecz"))) ? $post_data['fk_narzecz'] = $this->input->post("inputNaRzecz") : null;


                // TODO ::PRZENIEŚĆ PO LICZENIU:: jezeli jest status oplacono czesciowo, zweryfikuj pole zaplacono
                if ($this->input->post('inputStatus') == 3) {
                    $_oplacono = $this->custom_decimal($this->input->post('inputOplacono'));
                    if (!$_oplacono) {
                        $message = "Wartość zapłacono brutto nie jest liczbą";
                    }

                    if ($_oplacono > $lacznie_brutto) {
                        $message = "Opłacona kwota jest wyższa od kwoty brutto faktury";
                    }
                }
                $statusi = $this->input->post('inputStatus');

                switch ($statusi) {
                    case 1:
                        // do zapłaty
                        $dozaplaty = $lacznie_brutto;
                        $oplacono = 0;
                        break;
                    case 2:
                        // opłacony
                        $dozaplaty = 0;
                        $oplacono = $lacznie_brutto;
                        $op_data = date("Y-m-d");
                        break;
                    case 3:
                        // częściowo opłacony
                        $dozaplaty = $lacznie_brutto - $_oplacono;
                        $oplacono = $_oplacono;
                        break;
                    default:
                        break;
                }

                $this->db->insert('wydatki', $post_data);
                $status = 1;
                $id = $this->db->insert_id();


                $updateArray = array();

                for ($x = 0; $x < sizeof($batch_insertIDS); $x++) {

                    $updateArray[] = array(
                        'id_item' => $batch_insertIDS[$x],
                        'do_wydatku' => $id,
                    );
                }
                $this->db->update_batch('wydatki_wpisy', $updateArray, 'id_item');

                $platnosci = array(
                    'utworzenie_platnosci' => $this->input->post('inputData'),
                    'termin_platnosci' => $this->input->post('inputTermin'),
                    'status' => $statusi,
                    'zaplacona_kwota' => $oplacono,
                    'pozostala_kwota' => $dozaplaty,
                    'fk_wydatek' => $id,
                    'priorytet' => $this->input->post('inputPriorytet'),
                    'fk_rozbita' => $isRozbita,
                );
                if (isset($op_data)) {
                    $platnosci['oplacono'] = $op_data;
                }

                if (isset($zwroc_osobie)) {
                    $platnosci['zwroc'] = $zwroc_osobie;
                }
                $this->db->insert('platnosci', $platnosci);

                $pojazd = $this->input->post("inputPojazd");


                if (!empty($podkategoria_paliwo)) {
                    // kategoria paliwowa wec dodajemy  stan licznika
                    if ($pojazd) {
                        $pwydp = array(
                            'fk_wydatku' => $podkategoria_paliwo,
                            'fk_pojazd' => $this->input->post('inputPojazd'),
                            'litry' => $ilosclitrow
                        );

                        $this->db->insert('pojazdy_wydatki', $pwydp);

                        if (!$stanlicznika_nieznany) {
                            $stanlicz = array(
                                'wartosc' => $inputStanLicznika,
                                'FK_poj' => $this->input->post('inputPojazd'),
                                'kiedy' => $this->input->post('inputData')
                            );
                            $this->db->insert('pojazdy_przebiegi', $stanlicz);
                        }


                    }
                } elseif ($attach_car) {

                    foreach ($cat_ins_ids as $p) {
                        $pwydp = array(
                            'fk_wydatku' => $p,
                            'fk_pojazd' => $this->input->post('inputPojazd'),
                            'litry' => 0 // wszystkie pozostale kategorie nie paliwowe
                        );
                        $this->db->insert('pojazdy_wydatki', $pwydp);
                    }

                }


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
     * 
     * Done :
     * - Sprawdzanie czy dany dokument zostal dodany
     * - Sprwdzenie wartosci brutto-netto
     * - CSRF protection
     * - Powiazane faktury
     * - Kilka kategorii
     * - priorytet ::: pilny[1] istotny[2] normalne[3]
     * TODO :
     * - platnosci czesciowe DONE
     * - status zaplacono/czesciowo/w trakcie DONE
     * 
     * -- glowna kategoria - to kwota najwyzsza z faktury przy dodawaniu DONE
     * -- glowna kategoria sprawdza czy vat jest taki sam jak na podkategoriach DONE
     * -- wyszukiwanie po kontrahencie DONE
     * -- daty termin DONE
     * -- dynamic field bez szukaj
     * -- daty same + 1 -1 0
     * -- priority icons w liscie wydatkow  https://cdn.dribbble.com/users/401390/screenshots/2520549/jira-priority-icons.png DONE
      //////////////
     * 
     * -- cel zakupu dodawanie z lity / slownik
     * -- Paliwo -> auto z listy DONE
     * -- oplacanie faktur DONE
     * -- podlgad faktury DONE
     * -- zaplacenie w podgladzie faktury DONE
      zakres dat
      wyszkiwanie po priorytecie
     * 
     * -- auth sprawdzic
     * 
     * 
     * 
     * 
     * -- warunek zaplacono czesciowo trzeba wypelnic pole DONE
     *
     *      
     */
}
