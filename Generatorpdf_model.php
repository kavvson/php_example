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
class Generatorpdf_model extends CI_Model
{

    // Statusy
    const do_zaplaty = 1;
    const oplacony = 2;
    const czesciowo_oplacony = 3;

    public function __construct()
    {
        parent::__construct();
    }

    public function pobierz_delegacje($id)
    {
        $this->db->select("pracownik_delegacje.*,CONCAT(pracownicy.imie,' ',pracownicy.nazwisko) as kupujacy,pracownicy.konto as pkonto,b.*");
        $this->db->join('pracownicy', 'pracownik_delegacje.fk_pracownik = pracownicy.id_pracownika', 'left');
        $this->db->join('adresy b', 'pracownicy.fk_adres = b.id_adres');

        $this->db->where('pracownik_delegacje.id_delegajci', $id);
        $this->db->from("pracownik_delegacje");
        $query = $this->db->get()->result_array();

        return $query[0];
    }

    public function pobierz_wyplaty($id)
    {
        $this->db->select("pracownik_doreki.*,CONCAT(pracownicy.imie,' ',pracownicy.nazwisko) as kupujacy,pracownicy.konto as pkonto,b.*");
        $this->db->join('pracownicy', 'pracownik_doreki.fk_pracownik = pracownicy.id_pracownika', 'left');
        $this->db->join('adresy b', 'pracownicy.fk_adres = b.id_adres');


        $this->db->where('pracownik_doreki.id', $id);
        $this->db->from("pracownik_doreki");
        $query = $this->db->get()->result_array();

        return $query[0];
    }

    public function pobierz_zaliczke($id)
    {
        $this->db->select("pracownik_bank.typ_transakcji,abs(pracownik_bank.kwota) as kwota,pracownik_bank.data_operacji,pracownik_bank.opis,CONCAT(pracownicy.imie,' ',pracownicy.nazwisko) as kupujacy,pracownicy.konto as pkonto,b.*");
        $this->db->join('pracownicy', 'pracownik_bank.fk_pracownik = pracownicy.id_pracownika', 'left');
        $this->db->join('adresy b', 'pracownicy.fk_adres = b.id_adres');


        $this->db->where('pracownik_bank.id_transakcji', $id);
        $this->db->from("pracownik_bank");
        $query = $this->db->get()->result_array();

        return $query[0];
    }

    public function pobierz_wyplaty_pracownikow($month, $rok)
    {

        $dateclause = '';


        $query_date = $rok . '-' . $month . '-01';


        $dateclause .= 'data >= "' . date('Y-m-01', strtotime($query_date)) . '" AND ';
        $dateclause .= 'data <= "' . date('Y-m-t', strtotime($query_date)) . '"';


        $query = $this->db->query("SELECT pracownik_platnosci.*, CONCAT(pracownicy.imie,' ',pracownicy.nazwisko) as pracownik,pracownicy.konto FROM `pracownik_platnosci`
LEFT JOIN pracownicy ON pracownik_platnosci.fk_pracownik = pracownicy.id_pracownika
WHERE " . $dateclause);

        $query = $query->result_array();

        return $query;
    }

}
