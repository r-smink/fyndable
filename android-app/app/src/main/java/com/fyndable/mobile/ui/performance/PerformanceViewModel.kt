package com.fyndable.mobile.ui.performance

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.fyndable.mobile.data.api.FyndableApi
import com.fyndable.mobile.data.api.JsonUtils
import com.fyndable.mobile.data.model.RankKeyword
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class PerformanceViewModel(private val api: FyndableApi) : ViewModel() {

    sealed class UiState {
        data object Loading : UiState()
        data class Success(val ranks: List<RankKeyword>) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableStateFlow<UiState>(UiState.Loading)
    val state: StateFlow<UiState> = _state.asStateFlow()

    private val _toast = MutableStateFlow<String?>(null)
    val toast: StateFlow<String?> = _toast.asStateFlow()

    private val _isChecking = MutableStateFlow(false)
    val isChecking: StateFlow<Boolean> = _isChecking.asStateFlow()

    init { loadRanks() }

    fun loadRanks() {
        _state.value = UiState.Loading
        viewModelScope.launch {
            try {
                val resp = api.getRanks()
                if (resp.isSuccessful) {
                    _state.value = UiState.Success(JsonUtils.decodeFlexibleList<RankKeyword>(resp.body()))
                } else {
                    _state.value = UiState.Error("Fout: ${resp.code()}")
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "Onbekende fout")
            }
        }
    }

    fun checkRank(keyword: String) {
        _isChecking.value = true
        viewModelScope.launch {
            try {
                api.checkRankNow(mapOf("keyword" to keyword))
                _toast.value = "Ranking bijgewerkt!"
                loadRanks()
            } catch (e: Exception) {
                _toast.value = e.message
            } finally {
                _isChecking.value = false
            }
        }
    }

    fun clearToast() { _toast.value = null }
}
