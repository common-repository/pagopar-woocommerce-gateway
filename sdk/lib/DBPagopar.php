<?php

/**
 * Archivo de configuración de la base de datos de los pedidos
 * @author "Pagopar" <desarrollo@pagopar.com>
 * @version 1 8/5/2017
 */
class DBPagopar extends PDO {

    private $table = null;
    private $second_table = null;
    private $second_detail_table = null;
    /**
     * Setea el atributo PDO con la instancia enviada por parámetro
     * @param string $name Nombre de la base de Datos
     * @param string $user Nombre del usuario de la BD.
     * @param string $pass Contraseña de la BD.
     * @param string $host Nombre del host de la BD, por default "localhost".
     * @param string $table Nombre de la tabla de la BD, por default "transactions_pagopar".
     */
    public function __construct($name = null, $user = null, $pass = null, $host = "localhost", $table = "transactions_pagopar", $second_table = "pagopar_pagos_automaticos", $second_detail_table = "pagopar_pagos_automaticos_detalle") {
        $this->table = $table;
        $this->second_table = $second_table;
        $this->second_detail_table = $second_detail_table;

        $hostArray = explode(':', $host);

        if (count((array)$hostArray) === 2) {
            $port = $hostArray[1];
            $host = $hostArray[0];
            //Nueva conexión a la base de datos
            parent::__construct('mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name, $user, $pass, [PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } else {
            //Nueva conexión a la base de datos
            parent::__construct('mysql:host=' . $host . ';dbname=' . $name, $user, $pass, [PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }


        //Creación de la tabla si no existe
        $this->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id integer primary key,
                typeOrder varchar(120),
                totalAmount decimal(15,2),
                hash varchar(255),
                maxDateForPayment varchar(120), 
                buyerId integer,
                orderItems integer,
                description varchar(255), 
                created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP);');
        //created DATETIME DEFAULT CURRENT_TIMESTAMP);");
    }

    /**
     * Registra una nueva transacción en la tabla de transacciones y devuelve el ID del registro.
     * @param int $id Id de la transacción
     * @param string $typeOrder Tipo de orden,
     * @param float $totalAmount Monto total de la compra
     * @param string $hash Hash del pedido retornado por el webservice
     * @param string $maxDateForPayment Fecha máxima para el pago del pedido (Formato dd:mm:yyyy hh:mm:ss)
     * @param string $desc Descripción del pedido
     * @return int el número de transacción
     */
    public function insertTransaction($id, $typeOrder, $totalAmount, $hash, $maxDateForPayment, $desc) {
        $this->prepare("INSERT INTO " . $this->table . " (id,typeOrder,totalAmount,hash,maxDateForPayment,description) VALUES(?,?,?,?,?,?)")
                ->execute([$id, $typeOrder, $totalAmount, $hash, $maxDateForPayment, $desc]);
        return $this->lastInsertId();
    }

    public function insertSubscription($id, $fecha, $user_id, $activo) {
        $this->prepare("INSERT INTO " . $this->second_table . " (pago_id,order_id,fecha_creacion,user_id,activo) VALUES(?,?,?,?,?)")
                ->execute([$id, $id, $fecha, $user_id, $activo]);
        return $this->lastInsertId();
    }

    public function insertSubscriptionDetail($pago_id, $product_id, $pagado, $pago_anterior,$arealizar, $realizados, $proximo_pago) {
        $this->prepare("INSERT INTO " . $this->second_detail_table . " (pago_detalle_id,pago_id,product_id,pagado,fecha_ultimo_pago,pagos_a_realizar,pagos_realizados,fecha_proximo_pago) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([0,$pago_id, $product_id, $pagado, $pago_anterior,$arealizar, $realizados,$proximo_pago]);
        return $this->lastInsertId();
    }

    /**
     * Retorna la transacción que cumpla con los requisitos del where
     * @param string $where where statement
     * @return array
     */
    public function selectTransaction($where = '') {
        $sth = $this->prepare("SELECT * FROM " . $this->table . " WHERE {$where} LIMIT 1");
        $sth->execute();
        $result = $sth->fetchAll();
        if (empty($result)) {
            return false;
        }
        return $result[0];
    }

    /**
     * Actualiza una transacción, según los campos enviados por el parámetro.
     * @param array key=>value de los campos retornados desde el gateway
     * @param array key=>value para el where
     */
    public function updateTransaction(Array $data, Array $w) {
        $set = array();
        foreach (array_keys($data) as $key) {
            $set[] = "$key = :$key";
        }
        $where = array();
        foreach (array_keys($w) as $key) {
            $where[] = "$key = :$key";
        }

        $this->prepare("UPDATE " . $this->table . " SET  " . implode(",", $set)
                        . " where " . implode(" and ", $where))
                ->execute(array_merge($data, $w));
    }

}
