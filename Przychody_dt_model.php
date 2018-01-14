<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Przychody_dt_model
 *
 * @author Kavvson
 */
class Przychody_dt_model extends CI_Model {

    var $table = 'przychody_platnosci';
    var $column_order = array(
        'id_przychodu',
        "rejont",
        "wartosc",
        "netto",
        "vat_lacznie",
        "id_przychodu",
        null,
        "kontrah",
        null,
        "z_dnia",
        "termin_platnosci",
        "uwagi",
        null,
        "otrzymana_kwota",
        "pozostala_kwota",
    ); //set column field database for datatable orderable
    var $column_search = array(); //set column field database for datatable searchable 
    var $order = array("ddif", "asc"); // default order 

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    function rangeMonth($datestr) {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('Y-m-d', strtotime('first day of this month', $dt));
        $res['end'] = date('Y-m-d', strtotime('last day of this month', $dt));
        return $res;
    }

    function rangeWeek($datestr) {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
        $res['end'] = date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
        return $res;
    }

    private function _get_datatables_query() {

        $this->db->select("korekty_dokument.*,korekty_dokument.nazwa as kor_nazwa,przychody.*,kontrahenci.nazwa as kontrah,datediff(`przychody`.`termin_platnosci`,NOW()) as ddif,rejony.nazwa as rejont
,przychody_platnosci.otrzymana_kwota,przychody_platnosci.pozostala_kwota,przychody_platnosci.status as status");

        $this->db->join('przychody', 'przychody_platnosci.fk_przychodu = przychody.id_przychodu');
        $this->db->join('rejony', 'przychody.id_rejonu = rejony.id_rejonu', 'left');
        $this->db->join('kontrahenci', 'przychody.fk_kontrahent = kontrahenci.id_kontrahenta', 'left');
        $this->db->join('przychody_platnosci d', 'przychody.id_przychodu = d.fk_przychodu', 'left');


        $this->db->join('korekty_dokument', 'przychody.id_przychodu = korekty_dokument.fk_link', 'left');
      //  $this->db->join('przychody_korekta', 'przychody_wpisy.id_przychodu = przychody_korekta.fk_wpis_przy', 'left');



        if ($this->input->post('s_rejon')) {
            $this->db->where('`rejony`.`id_rejonu`', $this->input->post('s_rejon'));
        }

        if ($this->input->post('s_kontrahent')) {
            $this->db->where('przychody.fk_kontrahent', $this->input->post('s_kontrahent'));
        }

        // zakres dat
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

            $query_date = $_POST['customYear'].'-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('przychody.z_dnia >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('przychody.z_dnia <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $zakres = $this->input->post('s_zakres');
        // checkboxy

        $chkbox = $this->input->post('status_pl');
        if (!empty($chkbox)) {

            $this->db->group_start();
            foreach ($chkbox as $param) {


                switch ($param) {
                    case "zap" :
                        $this->db->where('`przychody_platnosci`.`pozostala_kwota`', '0');
                        break;
                    case "do_zap" :
                        $this->db->or_group_start();
                        $this->db->where('datediff(`przychody`.`termin_platnosci`, NOW()) >= ', '0');
                        $this->db->where('`przychody_platnosci`.`pozostala_kwota` >', '0');
                        $this->db->group_end();
                        break;
                    case "po_term" :
                        $this->db->or_group_start();
                        $this->db->where('datediff(`przychody`.`termin_platnosci`, NOW()) <', '0');
                        $this->db->where('`przychody_platnosci`.`pozostala_kwota` >', '0');
                        $this->db->group_end();
                        break;
                }
            }
            $this->db->group_end();
        }
        // DODAC : ostatni kwartal i pol roku

        switch ($zakres) {
            case "today" :
                $this->db->group_start();
                $today = date('Y-m-d');
                $this->db->where('przychody.z_dnia', $today);
                $this->db->group_end();
                break;
            case "yesterday" :
                $this->db->group_start();
                $yesterday = date('Y-m-d', strtotime("-1 days"));
                $this->db->where('przychody.z_dnia', $yesterday);
                $this->db->group_end();
                break;
            case "this_month" :
                $this->db->group_start();
                $range = $this->rangeMonth(date('Y-m-d'));
                $this->db->where('przychody.z_dnia >= ', $range['start']);
                $this->db->where('przychody.z_dnia <= ', $range['end']);
                $this->db->group_end();
                break;
            case "last_year" :
                $this->db->group_start();
                $this->db->where('przychody.z_dnia >= ', date("d-m-y",strtotime("last year January 1st")));
                $this->db->where('przychody.z_dnia <= ',date("d-m-y",strtotime("last year December 31st")));
                $this->db->group_end();
                break;
            case "this_year" :
                $yearEnd = date('Y-m-d', strtotime('last day of december'));
                $this->db->group_start();
                $this->db->where('przychody.z_dnia >= ', date('Y-m-d', strtotime('first day of january')));
                $this->db->where('przychody.z_dnia <= ', $yearEnd);
                $this->db->group_end();
                break;
            case "last_week" :
                $this->db->group_start();
                $lw = date('Y-m-d', strtotime("-7 days"));
                $range = $this->rangeMonth($lw);
                $this->db->where('przychody.z_dnia >= ', $range['start']);
                $this->db->where('przychody.z_dnia <= ', $range['end']);
                $this->db->group_end();
                break;
            case "last_month" :
                $this->db->group_start();
                $this->db->where('przychody.z_dnia >= ', date('Y-m-d', strtotime('first day of last month')));
                $this->db->where('przychody.z_dnia <= ', date('Y-m-d', strtotime('last day of last month')));
                $this->db->group_end();
                break;
             case "Q1" :
                $this->db->group_start();
                $this->db->where('przychody.z_dnia >= ', date('Y-m-d', strtotime('first day of january this year')));
                $this->db->where('przychody.z_dnia <= ', date('Y-m-d', strtotime('last day of march this year')));
                $this->db->group_end();
                break;
            case "Q2" :
                $this->db->group_start();
                $this->db->where('przychody.z_dnia >= ', date('Y-m-d', strtotime('first day of april this year')));
                $this->db->where('przychody.z_dnia <= ', date('Y-m-d', strtotime('last day of june this year')));
                $this->db->group_end();
                break;
            case "Q3" :
                $this->db->group_start();
                $this->db->where('przychody.z_dnia >= ', date('Y-m-d', strtotime('first day of july this year')));
                $this->db->where('przychody.z_dnia <= ', date('Y-m-d', strtotime('last day of september this year')));
                $this->db->group_end();
                break;
            case "Q4" :
                $this->db->group_start();
                $this->db->where('przychody.z_dnia >= ', date('Y-m-d', strtotime('first day of october this year')));
                $this->db->where('przychody.z_dnia <= ', date('Y-m-d', strtotime('last day of december this year')));
                $this->db->group_end();
                break;
            case "custom" :
                // todo custom
                $sd = $this->input->post("dateFrom");
                $fd = $this->input->post("dateTo");
                if (!empty($sd) && !empty($fd)) {
                    $this->db->group_start();
                    $this->db->where('przychody.z_dnia >= ', $sd);
                    $this->db->where('przychody.z_dnia <= ', $fd);
                    $this->db->group_end();
                }
                break;
        }


        $this->db->from($this->table);
        $i = 0;



        if (isset($_POST['order'])) { // here order processing
            $this->db->order_by($this->column_order[$_POST['order']['0']['column']], $_POST['order']['0']['dir']);
        } else if (isset($this->order)) {
            $order = $this->order;
            $this->db->order_by(key($order), $order[key($order)]);
        }
    }

    public function get_datatables() {
        $this->_get_datatables_query();
        $skip = FALSE;
        if (empty($this->input->post('length'))) {
            $l = 10;
            $s = 0;
        } else {
            $l = $this->input->post('length');
            $s = $this->input->post('start');
        }if ($this->input->post('length') == -1) {
            $skip = TRUE;
        }

        if (!$skip) {
            $this->db->limit($l, $s);
        }


        $query = $this->db->get();

       // var_dump($query->result());
     // echo $this->db->last_query();
        return $query->result();
    }

    public function agregacja($get) {

        /*
          pozostala_kwota
          zaplacona_kwota
          kwota_brutto
          wartosc_vat
          fk_rejon
          metoda_platnosci
          priorytet
          status

          switch ($statusi) {
          case 1:
          // do zapłaty
          $dozaplaty = 0;
          $oplacono = $brutto;
          break;
          case 2:
          // opłacony
          $dozaplaty = $brutto;
          $oplacono = 0;
          break;
          case 3:
          // częściowo opłacony
          $dozaplaty = $brutto - $ileoplacono;
          $oplacono = $ileoplacono;
          break;
          default:
          break;
          }

         */
        $lacznie_netto = 0;
        $lacznie_brutto = 0;
        $pozostala_kwota = 0;
        $zaplacona_kwota = 0;
        $status_do_zaplaty = 0;
        $status_oplacone = 0;
        $status_czesciowo = 0;
        $wartosc_vat = 0;
        $po_terminie = 0;
        if (!empty($get)) {
            foreach ($get as $a) {

                // Korekta //
                $korektavat = $a['nvat'];
                $korektanet = $a['nnet'];
                $korektabrutto = $a['nbrut'];

                if(!empty($korektabrutto) || !empty($korektanet) || !empty($korektavat))
                {
                    //if($korektabrutto > $lacznie_brutto){

                        $lacznie_brutto = bcadd($lacznie_brutto,$korektabrutto,2);
                     //   if($lacznie_netto !== $korektanet){
                          $lacznie_netto = bcadd($lacznie_netto, $korektanet, 2);
                    //    }

                        $wartosc_vat = bcadd($wartosc_vat,$korektavat, 2);

                   // }
                }else{
                    $lacznie_netto = bcadd($lacznie_netto, $a['netto'], 2);
                    $lacznie_brutto = bcadd($lacznie_brutto, $a['wartosc'], 2);
                    $wartosc_vat = bcadd($wartosc_vat, $a['vat_lacznie'], 2);
                }
                $pozostala_kwota = bcadd($pozostala_kwota, $a['pozostala_kwota'], 2);
                $zaplacona_kwota = bcadd($zaplacona_kwota, $a['otrzymana_kwota'], 2);


                switch ($a['status']) {
                    case 1:
                        // do zapłaty
                        $status_do_zaplaty = bcadd($status_do_zaplaty, 1);
                        break;
                    case 2:
                        // opłacony
                        $status_oplacone = bcadd($status_oplacone, 1);
                        break;
                    case 3:
                        // częściowo opłacony
                        $status_czesciowo = bcadd($status_czesciowo, 1);
                        break;
                    default:
                        break;
                }

                $r_dni = intval($a['ddif']);

                if (floatval($a['pozostala_kwota']) > 0) {
                    if ($r_dni < 0) {
                        // po terminie
                        $po_terminie++;
                        //echo $get['id_platnosci'];
                    }

                }
            }

        }

        return
                array(
                    'netto' => $lacznie_netto,
                    'brutto' => $lacznie_brutto,
                    'pozostala_kwota' => $pozostala_kwota,
                    'zaplacona_kwota' => $zaplacona_kwota,
                    'vat' => $wartosc_vat,
                    'po_terminie' => $po_terminie,
                    'status' => array(
                        "do_zaplaty" => $status_do_zaplaty,
                        "oplacone" => $status_oplacone,
                        "czesciowo" => $status_czesciowo
                    )
        );
    }

    public function count_filtered() {

        $this->_get_datatables_query();
        $query = $this->db->get();
        return array(
            'count' => $query->num_rows(),
            'agregacja' => $this->agregacja($query->result_array())
        );
    }

    public function count_all() {
        $this->db->from($this->table);
        return $this->db->count_all_results();
    }

    public function get_list_countries() {


        $countries = array();

        return $countries;
    }

}
