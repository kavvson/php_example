<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Delegacje_model extends CI_Model
{

    var $table = 'pracownik_delegacje';
    var $column_order = array(
        null,
        'fk_pracownik',
        'dstart',
        'dend',
        'kwota',
        'opis'

    ); //set column field database for datatable orderable
    var $column_search = array(); //set column field database for datatable searchable 
    var $order = array('dstart' => 'asc'); // default order

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
        $this->db->join('pracownicy', 'pracownik_delegacje.fk_pracownik = pracownicy.id_pracownika', 'left');

        //add custom filter here s_narzecz s_kontrakt

        if ($this->input->post('s_pracownik')) {
            $this->db->where('`fk_pracownik`', $this->input->post('s_pracownik'));
        }
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

            $query_date = $_POST['customYear'].'-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('dstart >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('dstart <=', date('Y-m-t', strtotime($query_date)));
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


}
