<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Generic_DT_model extends CI_Model
{

    public $table;
    public $column_order = array();
    public $column_search = array();
    public $order; // default order
    public $agregacja = FALSE;
    public $main_field = "";
    public $select = '*';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
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
        $this->db->select($this->select);

        if ((isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050) && !isset($_POST['customMonth'])) {
            $this->db->where($this->main_field . ' =', $_POST['customYear']);
        }
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

            $query_date = $_POST['customYear'].'-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where($this->main_field . ' >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where($this->main_field . ' <=', date('Y-m-t', strtotime($query_date)));
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
        //echo $this->db->last_query();
        return $query->result();
    }


    public function agregacja($get)
    {

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
                $lacznie_netto = bcadd($lacznie_netto, $a['kwota_netto'], 2);
                $lacznie_brutto = bcadd($lacznie_brutto, $a['kwota_brutto'], 2);
                $pozostala_kwota = bcadd($pozostala_kwota, $a['pozostala_kwota'], 2);
                $zaplacona_kwota = bcadd($zaplacona_kwota, $a['zaplacona_kwota'], 2);
                $wartosc_vat = bcadd($wartosc_vat, $a['wartosc_vat'], 2);

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

                    if ($a['ddif'] == 1) {
                        // jutro platnosc
                    }

                    if ($a['ddif'] == 0) {
                        // dzisiaj platnosc
                    }
                }
            }
            //var_dump($get);
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

    public function count_filtered()
    {

        $this->_get_datatables_query();
        $query = $this->db->get();
        $out = array();
        $out['count'] = $query->num_rows();
        if ($this->agregacja) {
            $out['agregacja'] = $this->agregacja($query->result_array());
        }
        return $out;
    }

    public function count_all()
    {
        $this->db->from($this->table);
        return $this->db->count_all_results();
    }


}
