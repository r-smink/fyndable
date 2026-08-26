package com.fyndable.mobile.ui.clusters

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.fyndable.mobile.data.api.FyndableApi
import com.fyndable.mobile.data.model.Cluster
import com.fyndable.mobile.data.model.ClusterItem
import com.fyndable.mobile.data.model.GenerateClusterRequest
import com.fyndable.mobile.data.model.GenerateContentRequest
import com.fyndable.mobile.data.model.ContentResult
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class ClustersViewModel(private val api: FyndableApi) : ViewModel() {

    sealed class UiState {
        data object Loading : UiState()
        data class Success(val clusters: List<Cluster>) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableStateFlow<UiState>(UiState.Loading)
    val state: StateFlow<UiState> = _state.asStateFlow()

    private val _selectedCluster = MutableStateFlow<Cluster?>(null)
    val selectedCluster: StateFlow<Cluster?> = _selectedCluster.asStateFlow()

    private val _isGenerating = MutableStateFlow(false)
    val isGenerating: StateFlow<Boolean> = _isGenerating.asStateFlow()

    private val _contentResult = MutableStateFlow<ContentResult?>(null)
    val contentResult: StateFlow<ContentResult?> = _contentResult.asStateFlow()

    private val _toast = MutableStateFlow<String?>(null)
    val toast: StateFlow<String?> = _toast.asStateFlow()

    init { loadClusters() }

    fun loadClusters() {
        _state.value = UiState.Loading
        viewModelScope.launch {
            try {
                val resp = api.getClusters()
                if (resp.isSuccessful) {
                    _state.value = UiState.Success(resp.body() ?: emptyList())
                } else {
                    _state.value = UiState.Error("Fout: ${resp.code()}")
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "Onbekende fout")
            }
        }
    }

    fun selectCluster(cluster: Cluster) {
        _selectedCluster.value = cluster
        viewModelScope.launch {
            try {
                val resp = api.getCluster(cluster.id)
                if (resp.isSuccessful) {
                    val detailed = resp.body() ?: cluster
                    _selectedCluster.value = detailed
                }
            } catch (e: Exception) {
                _toast.value = e.message
            }
        }
    }

    fun clearSelectedCluster() { _selectedCluster.value = null }

    fun generateCluster(topic: String, count: Int, language: String) {
        _isGenerating.value = true
        viewModelScope.launch {
            try {
                val resp = api.generateCluster(GenerateClusterRequest(topic, count, language))
                if (resp.isSuccessful) {
                    _toast.value = "Cluster gegenereerd!"
                    loadClusters()
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

    fun generateClusterContent(clusterId: Int, title: String, keyword: String) {
        _isGenerating.value = true
        _contentResult.value = null
        viewModelScope.launch {
            try {
                val resp = api.generateClusterContent(
                    GenerateContentRequest(title, keyword, 1500, clusterId)
                )
                if (resp.isSuccessful) {
                    _contentResult.value = resp.body()
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

    fun clearContentResult() { _contentResult.value = null }
    fun clearToast() { _toast.value = null }
}
