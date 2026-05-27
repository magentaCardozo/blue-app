<?php

class Param_MotifEnCours
{
    private $connection;
    private $table_name = 't_param_motif_en_cours';

    public $code;
    public $libelle;
    public $n_user_create;
    public $datesys;
    public $n_user_update;
    public $date_update;
    public $annule;
    public $n_user_annule;
    public $motif_annulation;
    public $date_synchro;
    public $is_sync;

    public function __construct($db)
    {
        $this->connection = $db;
    }

    // =========================
    // CREATE
    // =========================
    function Create()
    {
        try {
            // vérifier si existe déjà
            $check = "SELECT code FROM " . $this->table_name . " WHERE code = :code LIMIT 1";
            $stmt = $this->connection->prepare($check);
            $stmt->bindParam(":code", $this->code);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    "error" => 1,
                    "message" => "Ce code existe déjà"
                ];
            }

            $query = "INSERT INTO " . $this->table_name . "
                (code, libelle, n_user_create, datesys, annule)
                VALUES (:code, :libelle, :n_user_create, NOW(), 0)";

            $stmt = $this->connection->prepare($query);

            $this->code = htmlspecialchars(strip_tags($this->code));
            $this->libelle = htmlspecialchars(strip_tags($this->libelle));

            $stmt->bindParam(":code", $this->code);
            $stmt->bindParam(":libelle", $this->libelle);
            $stmt->bindParam(":n_user_create", $this->n_user_create);

            if ($stmt->execute()) {
                return [
                    "error" => 0,
                    "message" => "Motif créé avec succès"
                ];
            }

            return [
                "error" => 1,
                "message" => "Erreur lors de la création"
            ];
        } catch (Exception $e) {
            return [
                "error" => 1,
                "message" => $e->getMessage()
            ];
        }
    }

    // =========================
    // UPDATE
    // =========================
    function Modifier()
    {
        try {
            $query = "UPDATE " . $this->table_name . "
                SET libelle = :libelle,
                    n_user_update = :n_user_update,
                    date_update = NOW()
                WHERE code = :code";

            $stmt = $this->connection->prepare($query);

            $this->code = htmlspecialchars(strip_tags($this->code));
            $this->libelle = htmlspecialchars(strip_tags($this->libelle));

            $stmt->bindParam(":code", $this->code);
            $stmt->bindParam(":libelle", $this->libelle);
            $stmt->bindParam(":n_user_update", $this->n_user_update);

            if ($stmt->execute()) {
                return [
                    "error" => 0,
                    "message" => "Motif modifié avec succès"
                ];
            }

            return [
                "error" => 1,
                "message" => "Erreur lors de la modification"
            ];
        } catch (Exception $e) {
            return [
                "error" => 1,
                "message" => $e->getMessage()
            ];
        }
    }

    // =========================
    // DELETE (soft delete)
    // =========================
    function Supprimer()
    {
        try {
            $query = "UPDATE " . $this->table_name . "
                SET annule = 1,
                    n_user_annule = :n_user_annule
                WHERE code = :code";

            $stmt = $this->connection->prepare($query);

            $stmt->bindParam(":code", $this->code);
            $stmt->bindParam(":n_user_annule", $this->n_user_annule);

            if ($stmt->execute()) {
                return [
                    "error" => 0,
                    "message" => "Motif supprimé avec succès"
                ];
            }

            return [
                "error" => 1,
                "message" => "Erreur lors de la suppression"
            ];
        } catch (Exception $e) {
            return [
                "error" => 1,
                "message" => $e->getMessage()
            ];
        }
    }

    // =========================
    // READ (from file 1)
    // =========================
    function read()
    {
        $query = "SELECT code, libelle 
                  FROM " . $this->table_name . " 
                  WHERE annule = 0
                  ORDER BY libelle";

        $stmt = $this->connection->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    // =========================
    // Get a single motif as array
    // =========================
    function GetDetail()
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE code = ? LIMIT 0,1";
        $stmt = $this->connection->prepare($query);
        $this->code = strip_tags($this->code);
        $stmt->bindParam(1, $this->code);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row;
    }

    // =========================
    // Get a single motif and assign to object properties
    // =========================
    function GetDetailIN()
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE code = ? LIMIT 0,1";
        $stmt = $this->connection->prepare($query);
        $this->code = strip_tags($this->code);
        $stmt->bindParam(1, $this->code);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->code = $row['code'];
            $this->libelle = $row['libelle'];
            $this->n_user_create = $row['n_user_create'];
            $this->datesys = $row['datesys'];
            $this->n_user_update = $row['n_user_update'];
            $this->date_update = $row['date_update'];
            $this->annule = $row['annule'];
            $this->n_user_annule = $row['n_user_annule'];
            $this->motif_annulation = $row['motif_annulation'];
            $this->date_synchro = $row['date_synchro'];
            $this->is_sync = $row['is_sync'];
        }
    }
}