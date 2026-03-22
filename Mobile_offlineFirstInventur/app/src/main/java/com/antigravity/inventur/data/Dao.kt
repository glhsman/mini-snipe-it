package com.antigravity.inventur.data

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update

@Dao
interface InventoryDao {
    @Query("SELECT * FROM gfgh ORDER BY name_of")
    suspend fun getAllGfgh(): List<Gfgh>

    @Query("SELECT * FROM assets ORDER BY name_of")
    suspend fun getAllAssets(): List<Asset>

    @Query("SELECT * FROM sn WHERE sn = :serial")
    suspend fun getSn(serial: String): Sn?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOrUpdateSn(sn: Sn)
    
    // Add update query specifically if we want to avoid replacing full object if fields match
    // But REPLACE is fine for this MVP
    @Query("UPDATE sn SET assetref = :assetRef, gfghref = :gfghRef, raum = :raum, bemerkung = :bemerkung WHERE sn = :serial")
    suspend fun updateSnDetails(serial: String, assetRef: Int?, gfghRef: Int?, raum: String?, bemerkung: String?)
}
