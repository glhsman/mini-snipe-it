package com.antigravity.inventur.data

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow

class InventoryRepository(private val dao: InventoryDao) {
    suspend fun getAllGfgh(): List<Gfgh> = dao.getAllGfgh()
    
    suspend fun getAllAssets(): List<Asset> = dao.getAllAssets()
    
    suspend fun getSn(serial: String): Sn? = dao.getSn(serial)
    
    suspend fun saveSn(sn: Sn) = dao.insertOrUpdateSn(sn)

    suspend fun updateSnDetails(serial: String, assetRef: Int?, gfghRef: Int?, raum: String?, bemerkung: String?) {
        dao.updateSnDetails(serial, assetRef, gfghRef, raum, bemerkung)
    }
}
