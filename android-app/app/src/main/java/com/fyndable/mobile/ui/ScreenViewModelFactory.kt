package com.fyndable.mobile.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.fyndable.mobile.data.api.NetworkModule
import com.fyndable.mobile.data.store.AuthStore

class ScreenViewModelFactory(
    private val authStore: AuthStore
) : ViewModelProvider.Factory {

    private val api by lazy { NetworkModule.getApi(authStore) }

    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        return when {
            modelClass.isAssignableFrom(com.fyndable.mobile.ui.keywords.KeywordsViewModel::class.java) ->
                com.fyndable.mobile.ui.keywords.KeywordsViewModel(api) as T
            modelClass.isAssignableFrom(com.fyndable.mobile.ui.clusters.ClustersViewModel::class.java) ->
                com.fyndable.mobile.ui.clusters.ClustersViewModel(api) as T
            modelClass.isAssignableFrom(com.fyndable.mobile.ui.generate.GenerateViewModel::class.java) ->
                com.fyndable.mobile.ui.generate.GenerateViewModel(api) as T
            modelClass.isAssignableFrom(com.fyndable.mobile.ui.posts.PostsViewModel::class.java) ->
                com.fyndable.mobile.ui.posts.PostsViewModel(api) as T
            modelClass.isAssignableFrom(com.fyndable.mobile.ui.performance.PerformanceViewModel::class.java) ->
                com.fyndable.mobile.ui.performance.PerformanceViewModel(api) as T
            else -> throw IllegalArgumentException("Unknown ViewModel class: ${modelClass.name}")
        }
    }
}
