package com.antigravity.inventur.data

import androidx.room.Entity
import androidx.room.PrimaryKey
import androidx.room.ColumnInfo

@Entity(tableName = "gfgh")
data class Gfgh(
    @PrimaryKey
    @ColumnInfo(name = "gfghid") val id: Int,
    @ColumnInfo(name = "name_of") val name: String?,
    @ColumnInfo(name = "strasse") val strasse: String?,
    @ColumnInfo(name = "hausnummer") val hausnummer: String?,
    @ColumnInfo(name = "plz") val plz: String?,
    @ColumnInfo(name = "ort") val ort: String?
)

@Entity(tableName = "assets") // or "asset"? Checked schema: "assets"
data class Asset(
    @PrimaryKey
    @ColumnInfo(name = "assetid") val id: Int,
    @ColumnInfo(name = "name_of") val name: String?
)

@Entity(tableName = "sn")
data class Sn(
    @PrimaryKey
    @ColumnInfo(name = "sn") val sn: String,
    @ColumnInfo(name = "assetref") val assetRef: Int?,
    @ColumnInfo(name = "gfghref") val gfghRef: Int?,
    @ColumnInfo(name = "bemerkung") val bemerkung: String?,
    @ColumnInfo(name = "raum") val raum: String?,
    @ColumnInfo(name = "aktiv") val aktiv: Int?,
    @ColumnInfo(name = "inventarnr") val inventarNr: String?
)
