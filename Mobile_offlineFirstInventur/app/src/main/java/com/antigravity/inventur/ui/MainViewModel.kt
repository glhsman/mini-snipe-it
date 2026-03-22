package com.antigravity.inventur.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.antigravity.inventur.data.Asset
import com.antigravity.inventur.data.Gfgh
import com.antigravity.inventur.data.InventoryRepository
import com.antigravity.inventur.data.Sn
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class MainViewModel(private val repository: InventoryRepository) : ViewModel() {

    private val _companies = MutableStateFlow<List<Gfgh>>(emptyList())
    val companies: StateFlow<List<Gfgh>> = _companies.asStateFlow()

    private val _assets = MutableStateFlow<List<Asset>>(emptyList())
    val assets: StateFlow<List<Asset>> = _assets.asStateFlow()

    private val _selectedCompany = MutableStateFlow<Gfgh?>(null)
    val selectedCompany: StateFlow<Gfgh?> = _selectedCompany.asStateFlow()

    private val _scannedSn = MutableStateFlow<Sn?>(null)
    val scannedSn: StateFlow<Sn?> = _scannedSn.asStateFlow()

    // Form State
    private val _currentSnText = MutableStateFlow("")
    val currentSnText: StateFlow<String> = _currentSnText.asStateFlow()

    private val _selectedAsset = MutableStateFlow<Asset?>(null)
    val selectedAsset: StateFlow<Asset?> = _selectedAsset.asStateFlow()

    private val _location = MutableStateFlow("")
    val location: StateFlow<String> = _location.asStateFlow()

    private val _comment = MutableStateFlow("")
    val comment: StateFlow<String> = _comment.asStateFlow()
    
    private val _message = MutableStateFlow<String?>(null)
    val message: StateFlow<String?> = _message.asStateFlow()

    init {
        loadData()
    }

    private fun loadData() {
        viewModelScope.launch {
            _companies.value = repository.getAllGfgh()
            _assets.value = repository.getAllAssets()
        }
    }

    fun selectCompany(gfgh: Gfgh) {
        _selectedCompany.value = gfgh
    }

    fun onSnEntered(sn: String) {
        _currentSnText.value = sn
        viewModelScope.launch {
            val existing = repository.getSn(sn)
            if (existing != null) {
                _scannedSn.value = existing
                // Pre-fill fields
                _selectedAsset.value = _assets.value.find { it.id == existing.assetRef }
                _location.value = existing.raum ?: ""
                _comment.value = existing.bemerkung ?: ""
                _message.value = "SN gefunden: ${existing.sn}"
            } else {
                _scannedSn.value = null
                _selectedAsset.value = null
                _location.value = ""
                _comment.value = ""
                _message.value = "Neue SN"
            }
            if(sn.isEmpty()) {
                _message.value = null
            }
        }
    }

    fun onAssetSelected(asset: Asset) {
        _selectedAsset.value = asset
    }

    fun onLocationChanged(loc: String) {
        _location.value = loc
    }

    fun onCommentChanged(comm: String) {
        _comment.value = comm
    }

    fun saveEntry() {
        val snText = _currentSnText.value
        if (snText.isBlank()) {
            _message.value = "Bitte SN eingeben"
            return
        }
        val company = _selectedCompany.value
        if (company == null) {
            _message.value = "Keine Gesellschaft ausgewählt"
            return
        }

        viewModelScope.launch {
            val newSn = Sn(
                sn = snText,
                assetRef = _selectedAsset.value?.id,
                gfghRef = company.id,
                raum = _location.value,
                bemerkung = _comment.value,
                aktiv = 1, // Default active
                inventarNr = _scannedSn.value?.inventarNr // Preserve if exists, else null
            )
            repository.saveSn(newSn)
            _message.value = "Gespeichert!"
            // Reset fields or keep them? Usually clear for next scan
            // onSnEntered("") // Optional: Clear after save
        }
    }
    
    fun clearMessage() {
        _message.value = null
    }
}

class MainViewModelFactory(private val repository: InventoryRepository) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(MainViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return MainViewModel(repository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
