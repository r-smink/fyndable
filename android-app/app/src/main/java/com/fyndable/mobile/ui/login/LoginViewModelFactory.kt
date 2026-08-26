package com.fyndable.mobile.ui.login

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.fyndable.mobile.data.store.AuthStore

class LoginViewModelFactory(
    private val authStore: AuthStore
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(LoginViewModel::class.java)) {
            return LoginViewModel(authStore) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
