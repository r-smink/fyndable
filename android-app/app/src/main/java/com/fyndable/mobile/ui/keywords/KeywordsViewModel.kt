package com.fyndable.mobile.ui.keywords

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.fyndable.mobile.data.api.FyndableApi
import com.fyndable.mobile.data.model.AddKeywordRequest
import com.fyndable.mobile.data.model.GenerateKeywordsRequest
import com.fyndable.mobile.data.model.Keyword
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class KeywordsViewModel(private val api: FyndableApi) : ViewModel() {

    sealed class UiState {
        data object Loading : UiState()
        data class Success(val keywords: List<Keyword>) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableStateFlow<UiState>(UiState.Loading)
    val state: StateFlow<UiState> = _state.asStateFlow()

    private val _generatedKeywords = MutableStateFlow<List<Keyword>>(emptyList())
    val generatedKeywords: StateFlow<List<Keyword>> = _generatedKeywords.asStateFlow()

    private val _isGenerating = MutableStateFlow(false)
    val isGenerating: StateFlow<Boolean> = _isGenerating.asStateFlow()

    private val _toast = MutableStateFlow<String?>(null)
    val toast: StateFlow<String?> = _toast.asStateFlow()

    init { loadKeywords() }

    fun loadKeywords() {
        _state.value = UiState.Loading
        viewModelScope.launch {
            try {
                val resp = api.getKeywords(100)
                if (resp.isSuccessful) {
                    val kws = resp.body() ?: emptyList()
                    _state.value = UiState.Success(kws)
                } else {
                    _state.value = UiState.Error("Fout: ${resp.code()}")
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "Onbekende fout")
            }
        }
    }

    fun addKeyword(keyword: String, searchVolume: Int? = null, difficulty: Int? = null) {
        viewModelScope.launch {
            try {
                val resp = api.addKeyword(AddKeywordRequest(keyword, searchVolume, difficulty))
                if (resp.isSuccessful) {
                    _toast.value = "Keyword toegevoegd"
                    loadKeywords()
                } else {
                    _toast.value = "Fout: ${resp.code()}"
                }
            } catch (e: Exception) {
                _toast.value = e.message
            }
        }
    }

    fun generateKeywords(topic: String) {
        _isGenerating.value = true
        viewModelScope.launch {
            try {
                val resp = api.generateKeywords(GenerateKeywordsRequest(topic))
                if (resp.isSuccessful) {
                    val body = resp.body()
                    _generatedKeywords.value = body ?: emptyList()
                } else {
                    _toast.value = "Fout: ${resp.code()}"
                }
            } catch (e: Exception) {
                _toast.value = e.message
            } finally {
                _isGenerating.value = false
            }
        }
    }

    fun saveGeneratedKeyword(keyword: Keyword) {
        viewModelScope.launch {
            try {
                api.addKeyword(AddKeywordRequest(keyword.keyword, keyword.searchVolume, keyword.difficulty))
                _toast.value = "Opgeslagen: ${keyword.keyword}"
            } catch (e: Exception) {
                _toast.value = e.message
            }
        }
    }

    fun clearToast() { _toast.value = null }
    fun clearGenerated() { _generatedKeywords.value = emptyList() }
}
