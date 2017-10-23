<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Place_model extends CI_Model
{

    var $table = 'pracownik_place';
    var $column_order = array(
        null,
        'fk_prac',
        'miesiac',
        'data_wyplaty',
        'brutto',
        'zus_pracownik',
        'zus_pracodawca',
        'do_wyplaty',
        'obciazenie',
    ); //set column field database for datatable orderable
    var $column_search = array(); //set column field database for datatable searchable 
    var $order = array('miesiac' => 'asc'); // default order 

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    function dateDiff($start, $end)
    {

        $start_ts = strtotime($start);

        $end_ts = strtotime($end);

        $diff = $end_ts - $start_ts;

        return round($diff / 86400);

    }

    protected function delegacjaExists($s, $e, $p)
    {

        try {
            $this->db->trans_begin();
            $this->db->where('pracownik_delegacje.dstart', $s);
            $this->db->where('pracownik_delegacje.dend', $e);
            $this->db->where('pracownik_delegacje.fk_pracownik', $p);
            $this->db->from("pracownik_delegacje");
            $query = $this->db->get();

            $wartosci = $query->result_array();


            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }

        return (count($wartosci) >= 1) ? TRUE : FALSE;
    }

    /*
     * Umowy inne
     *
     * Delegacje
     * dateDiff = 0 ->15zl
     * dateDiff >= 1 -> 30zl
     * dateDiff >= 2 -> 60 +  if(dateDiff > 2) { ( dodaj_tyle ( $diff - 2 ) * 30zl ); }else{ add 0zł }
     *
     * 2 zakladka olac tych pracownikow ktocyh nie ma w bazie
     *
     * Premie
     * Karta +-
     * Podatki {nie ma info jeszcze}
     * Podstumowanie {nie ma info jeszcze}
     *
     * Delegacje
     */
    public function obliczDelegacje()
    {
        // input start
        // input end
        // dateDiff

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $start = $this->input->post("dateFromm");
        $end = $this->input->post("dateTomm");

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('dateFromm'))) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $this->input->post('dateTomm'))) {
            $message = "Nieprawidłowy format daty terminu płatności rrrr-mm-dd";
        }

        if (strlen($this->input->post('delegacjaOpis')) > 250 || empty($this->input->post('delegacjaOpis'))) {
            $message = "Opis musi się składać od 1 znaku do 250 znaków";
        }


        $roznica = $this->dateDiff($start, $end);
        $stawka = 0;
        $status = 0;
        $message = "";

        switch ($roznica) {
            case 0 :
                $stawka = 15;
                $status = 1;
                break;
            case ($roznica >= 1 and $roznica <= 2) :
                $stawka = 60;
                $status = 1;
                break;
            case ($roznica > 2) :
                $stawka = 60;
                if ($roznica >= 3) {
                    $dodatek = bcmul(bcsub($roznica, 1), 30);
                    $stawka = bcadd($stawka, $dodatek);
                }
                $status = 1;
                break;
            default:

                break;
        }
        if (empty($this->input->post("fk_prac"))) {
            $status = 0;
            $message = "Błąd1";
        }
        if ($this->delegacjaExists($start, $end, $this->input->post("fk_prac"))) {
            $message = "Podana delegacja znajduje się już w bazie danych";
            $status = 0;
        }

        try {
            $this->db->trans_begin();
            if ($status) {
                $post_data = array(
                    'dstart	' => $this->input->post('dateFromm'),
                    'dend	' => $this->input->post('dateTomm'),
                    'kwota	' => $stawka,
                    'opis	' => $this->input->post('delegacjaOpis'),
                    'fk_pracownik	' => $this->input->post('fk_prac'),

                );


                $this->db->insert('pracownik_delegacje', $post_data);
                $status = 1;
                $id = $this->db->insert_id();
            } else {
                if (empty($message)) {
                    $message = "Błąd2";
                }

            }

            if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
                if (is_numeric($id)) {
                    if ($status) {
                        $message = "Dodano delegację, kwota : " . $stawka . " zł";
                    }
                }
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }


        $responce = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $responce, "response" => array("status" => $status, "message" => $message))));

    }

    function rangeMonth($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('Y-m-d', strtotime('first day of this month', $dt));
        $res['end'] = date('Y-m-d', strtotime('last day of this month', $dt));
        return $res;
    }

    function rangeWeek($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
        $res['end'] = date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
        return $res;
    }

    private function _get_datatables_query()
    {

        $this->db->select("*");
        $this->db->join('pracownicy', 'pracownik_place.fk_prac = pracownicy.id_pracownika', 'left');

        //add custom filter here s_narzecz s_kontrakt

        if ($this->input->post('s_pracownik')) {
            $this->db->where('`fk_prac`', $this->input->post('s_pracownik'));
        }

        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'].'-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('`pracownik_place`.`miesiac` >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('`pracownik_place`.`miesiac` <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }

        $zakres = $this->input->post('s_zakres');
        switch ($zakres) {
            case "today" :
                $this->db->group_start();
                $today = date('Y-m-d');
                $this->db->where('`pracownik_place`.`miesiac`', $today);
                $this->db->group_end();
                break;
            case "yesterday" :
                $this->db->group_start();
                $yesterday = date('Y-m-d', strtotime("-1 days"));
                $this->db->where('`pracownik_place`.`miesiac`', $yesterday);
                $this->db->group_end();
                break;
            case "this_month" :
                $this->db->group_start();
                $range = $this->rangeMonth(date('Y-m-d'));
                $this->db->where('`pracownik_place`.`miesiac` >=', $range['start']);
                $this->db->where('`pracownik_place`.`miesiac` <=', $range['end']);
                $this->db->group_end();

                break;
            case "this_year" :
                $this->db->group_start();
                $this->db->where('`pracownik_place`.`miesiac` >=', date('l', strtotime(date('Y-01-01'))));
                $this->db->group_end();
                break;
            case "Q1" :
                $this->db->group_start();
                $this->db->where('`pracownik_place`.`miesiac` >= ', date('Y-m-d', strtotime('first day of january this year')));
                $this->db->where('`pracownik_place`.`miesiac` <= ', date('Y-m-d', strtotime('last day of march this year')));
                $this->db->group_end();
                break;
            case "Q2" :
                $this->db->group_start();
                $this->db->where('`pracownik_place`.`miesiac` >= ', date('Y-m-d', strtotime('first day of april this year')));
                $this->db->where('`pracownik_place`.`miesiac` <= ', date('Y-m-d', strtotime('last day of june this year')));
                $this->db->group_end();
                break;
            case "Q3" :
                $this->db->group_start();
                $this->db->where('`pracownik_place`.`miesiac` >= ', date('Y-m-d', strtotime('first day of july this year')));
                $this->db->where('`pracownik_place`.`miesiac` <= ', date('Y-m-d', strtotime('last day of september this year')));
                $this->db->group_end();
                break;
            case "Q4" :
                $this->db->group_start();
                $this->db->where('`pracownik_place`.`miesiac` >= ', date('Y-m-d', strtotime('first day of october this year')));
                $this->db->where('`pracownik_place`.`miesiac` <= ', date('Y-m-d', strtotime('last day of december this year')));
                $this->db->group_end();
                break;
            case "last_week" :
                $this->db->group_start();
                $lw = date('Y-m-d', strtotime("-7 days"));
                $range = $this->rangeMonth($lw);
                $this->db->where('`pracownik_place`.`miesiac` >=', $range['start']);
                $this->db->where('`pracownik_place`.`miesiac` <=', $range['end']);
                $this->db->group_end();
                break;
            case "last_month" :
                $this->db->group_start();
                $this->db->where('`pracownik_place`.`miesiac` >=', date('Y-m-d', strtotime('first day of last month')));
                $this->db->where('`pracownik_place`.`miesiac` <=', date('Y-m-d', strtotime('last day of last month')));
                $this->db->group_end();
                break;
            case "custom" :
                // todo custom
                $sd = $this->input->post("dateFrom");
                $fd = $this->input->post("dateTo");
                if (!empty($sd) && !empty($fd)) {
                    $this->db->group_start();
                    $this->db->where('`pracownik_place`.`miesiac` >=', $sd);
                    $this->db->where('`pracownik_place`.`miesiac` <=', $fd);
                    $this->db->group_end();
                }
                break;
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
        //echo $this->db->last_query();
        return $query->result();
    }

    public function agregacja($get)
    {

        /*
          $row["id_placy"] = $customers->id_placy;
          $row["fk_prac"] = $customers->fk_prac;
          $row["miesiac"] = $customers->miesiac;
          $row["data_wyplaty"] = $customers->data_wyplaty;
          $row["brutto"] = $customers->brutto;
          $row["zus_pracownik"] = $customers->zus_pracownik;
          $row["zus_pracodawca"] = $customers->zus_pracodawca;
          $row["do_wyplaty"] = $customers->do_wyplaty;
          $row["obciazenie"] = $customers->obciazenie;
         */
        $zus_pracownik = 0;
        $lacznie_brutto = 0;
        $zus_pracodawca = 0;
        $do_wyplaty = 0;
        $obciazenie = 0;

        if (!empty($get)) {
            foreach ($get as $a) {

                $lacznie_brutto = bcadd($lacznie_brutto, $a['brutto'], 2);
                $zus_pracownik = bcadd($zus_pracownik, $a['zus_pracownik'], 2);
                $zus_pracodawca = bcadd($zus_pracodawca, $a['zus_pracodawca'], 2);
                $do_wyplaty = bcadd($do_wyplaty, $a['do_wyplaty'], 2);
                $obciazenie = bcadd($obciazenie, $a['obciazenie'], 2);
            }
        }

        return
            array(
                'zus_pracownik' => $zus_pracownik,
                'brutto' => $lacznie_brutto,
                'zus_pracodawca' => $zus_pracodawca,
                'zus_lacznie' => bcadd($zus_pracodawca, $zus_pracownik),
                'do_wyplaty' => $do_wyplaty,
                'obciazenie' => $obciazenie,
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

    public function get_list_countries()
    {


        $countries = array();

        return $countries;
    }

}
