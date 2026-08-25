package com.fyndable.mobile.ui.generate

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.fyndable.mobile.data.api.FyndableApi
import com.fyndable.mobile.data.model.ContentResult
import com.fyndable.mobile.data.model.WriteArticleRequest
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class GenerateViewModel(private val api: FyndableApi) : ViewModel() {

    sealed class UiState {
        data object Idle : UiState()
        data object Loading : UiState()
        data class Success(val result: ContentResult) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableStateFlow<UiState>(UiState.Idle)
    val state: StateFlow<UiState> = _state.asStateFlow()

    fun generateArticle(
        keyword: String,
        title: String?,
        wordCount: Int,
        tone: String,
        includeFaq: Boolean,
        createDraft: Boolean
    ) {
        if (keyword.isBlank()) return
        _state.value = UiState.Loading
        viewModelScope.launch {
            try {
                val resp = api.writeArticle(
                    WriteArticleRequest(
                        keyword = keyword,
                        title = title?.takeIf { it.isNotBlank() },
                        tone = tone,
                        wordCount = wordCount,
                        includeFaq = includeFaq,
                        createDraft = createDraft
                    )
                )
                if (resp.isSuccessful) {
                    val body = resp.body()
                    if (body != null) {
                        _state.value = UiState.Success(body)
                    } else {
                        _state.value = UiState.Error("Geen content ontvangen")
                    }
                } else {
                    _state.value = UiState.Error("Fout: ${resp.code()}")
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "Onbekende fout")
            }
        }
    }

    fun reset() { _state.value = UiState.Idle }
}
