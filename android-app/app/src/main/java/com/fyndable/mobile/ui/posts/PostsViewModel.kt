package com.fyndable.mobile.ui.posts

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.fyndable.mobile.data.api.FyndableApi
import com.fyndable.mobile.data.model.CreatedPost
import com.fyndable.mobile.data.model.DeletePostRequest
import com.fyndable.mobile.data.model.PostStats
import com.fyndable.mobile.data.model.UpdatePostRequest
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class PostsViewModel(private val api: FyndableApi) : ViewModel() {

    sealed class UiState {
        data object Loading : UiState()
        data class Success(val posts: List<CreatedPost>, val stats: PostStats?) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableStateFlow<UiState>(UiState.Loading)
    val state: StateFlow<UiState> = _state.asStateFlow()

    private val _selectedPost = MutableStateFlow<CreatedPost?>(null)
    val selectedPost: StateFlow<CreatedPost?> = _selectedPost.asStateFlow()

    private val _toast = MutableStateFlow<String?>(null)
    val toast: StateFlow<String?> = _toast.asStateFlow()

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    init { loadPosts() }

    fun loadPosts() {
        _state.value = UiState.Loading
        viewModelScope.launch {
            try {
                val postsResp = api.getCreatedPosts(50)
                val statsResp = api.getPostStats()
                if (postsResp.isSuccessful) {
                    val posts = postsResp.body() ?: emptyList()
                    val stats = if (statsResp.isSuccessful) statsResp.body()?.stats else null
                    _state.value = UiState.Success(posts, stats)
                } else {
                    _state.value = UiState.Error("Fout: ${postsResp.code()}")
                }
            } catch (e: Exception) {
                _state.value = UiState.Error(e.message ?: "Onbekende fout")
            }
        }
    }

    fun selectPost(post: CreatedPost) {
        _selectedPost.value = post
        viewModelScope.launch {
            try {
                val resp = api.getPost(post.ID ?: post.id ?: return@launch)
                if (resp.isSuccessful) {
                    _selectedPost.value = resp.body()
                }
            } catch (e: Exception) {
                _toast.value = e.message
            }
        }
    }

    fun clearSelectedPost() { _selectedPost.value = null }

    fun schedulePost(id: Int, dateTime: String) {
        _isLoading.value = true
        viewModelScope.launch {
            try {
                val resp = api.updatePost(id, UpdatePostRequest("future", dateTime))
                if (resp.isSuccessful) {
                    _toast.value = "Bericht ingepland!"
                    clearSelectedPost()
                    loadPosts()
                } else {
                    _toast.value = "Fout: ${resp.code()}"
                }
            } catch (e: Exception) {
                _toast.value = e.message
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun publishPost(id: Int) {
        _isLoading.value = true
        viewModelScope.launch {
            try {
                val resp = api.updatePost(id, UpdatePostRequest("publish"))
                if (resp.isSuccessful) {
                    _toast.value = "Bericht gepubliceerd!"
                    clearSelectedPost()
                    loadPosts()
                } else {
                    _toast.value = "Fout: ${resp.code()}"
                }
            } catch (e: Exception) {
                _toast.value = e.message
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun unpublishPost(id: Int) {
        _isLoading.value = true
        viewModelScope.launch {
            try {
                val resp = api.updatePost(id, UpdatePostRequest("draft"))
                if (resp.isSuccessful) {
                    _toast.value = "Bericht gedepubliceerd"
                    clearSelectedPost()
                    loadPosts()
                } else {
                    _toast.value = "Fout: ${resp.code()}"
                }
            } catch (e: Exception) {
                _toast.value = e.message
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun deletePost(id: Int) {
        _isLoading.value = true
        viewModelScope.launch {
            try {
                val resp = api.deletePost(id, DeletePostRequest(listOf(id)))
                if (resp.isSuccessful) {
                    _toast.value = "Bericht verwijderd"
                    clearSelectedPost()
                    loadPosts()
                } else {
                    _toast.value = "Fout: ${resp.code()}"
                }
            } catch (e: Exception) {
                _toast.value = e.message
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun clearToast() { _toast.value = null }
}
