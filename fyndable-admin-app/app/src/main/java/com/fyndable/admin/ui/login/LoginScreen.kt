package com.fyndable.admin.ui.login

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.prefs.CredentialStore
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.LicenseRepository
import com.fyndable.admin.ui.theme.FyndableBlue
import com.fyndable.admin.ui.theme.FyndablePurple
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

@HiltViewModel
class AuthViewModel @Inject constructor(
    private val credentialStore: CredentialStore,
    private val licenseRepository: LicenseRepository,
) : ViewModel() {

    private val _isLoggedIn = MutableStateFlow<Boolean?>(null)
    val isLoggedIn: StateFlow<Boolean?> = _isLoggedIn.asStateFlow()

    init {
        _isLoggedIn.value = credentialStore.hasCredentials()
    }

    fun refresh() {
        _isLoggedIn.value = credentialStore.hasCredentials()
    }

    fun login(username: String, appPassword: String, onResult: (Boolean, String?) -> Unit) {
        viewModelScope.launch {
            credentialStore.setCredentials(username, appPassword)
            when (val result = licenseRepository.ping()) {
                is ApiResult.Success -> {
                    _isLoggedIn.value = true
                    onResult(true, null)
                }
                is ApiResult.Error -> {
                    credentialStore.clearAll()
                    _isLoggedIn.value = false
                    onResult(false, result.message)
                }
            }
        }
    }

    fun logout() {
        credentialStore.clearAll()
        _isLoggedIn.value = false
    }
}

@Composable
fun LoginScreen(
    onLoggedIn: () -> Unit,
    viewModel: AuthViewModel = hiltViewModel(),
) {
    var username by remember { mutableStateOf("") }
    var appPassword by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var loading by remember { mutableStateOf(false) }

    val gradient = Brush.verticalGradient(
        colors = listOf(FyndablePurple, FyndableBlue)
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(Color(0xFFF8FAFC)),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Top Banner
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(280.dp)
                .background(gradient),
            contentAlignment = Alignment.Center
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Surface(
                    modifier = Modifier.size(72.dp),
                    shape = CircleShape,
                    color = Color.White.copy(alpha = 0.2f),
                    border = BorderStroke(2.dp, Color.White)
                ) {
                    Box(modifier = Modifier.fillMaxSize()) {
                        // Main large 'F' centered
                        Text(
                            text = "F",
                            fontSize = 40.sp,
                            fontWeight = FontWeight.Black,
                            color = Color.White,
                            modifier = Modifier.align(Alignment.Center)
                        )
                        // Small capt 'A' in the top right corner
                        Text(
                            text = "A",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                            modifier = Modifier
                                .align(Alignment.TopEnd)
                                .padding(top = 8.dp, end = 12.dp)
                        )
                    }
                }
                Spacer(Modifier.height(16.dp))
                Text(
                    text = "Fyndable Ops",
                    style = MaterialTheme.typography.headlineMedium,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "Internal SaaS portal",
                    style = MaterialTheme.typography.bodyLarge,
                    color = Color.White.copy(alpha = 0.8f)
                )
            }
        }

        Card(
            modifier = Modifier
                .fillMaxWidth()
                .offset(y = (-40).dp)
                .padding(horizontal = 24.dp),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
            border = BorderStroke(1.dp, Color(0xFFE2E8F0))
        ) {
            Column(
                modifier = Modifier.padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "Welcome Back",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF1E293B)
                )
                Spacer(Modifier.height(8.dp))
                Text(
                    text = "Log in to manage your SaaS tenants",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color(0xFF64748B),
                    textAlign = TextAlign.Center
                )
                Spacer(Modifier.height(24.dp))

                OutlinedTextField(
                    value = username,
                    onValueChange = { username = it },
                    label = { Text("WordPress Username") },
                    leadingIcon = { Icon(Icons.Filled.Person, contentDescription = null, tint = FyndablePurple) },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = androidx.compose.material3.OutlinedTextFieldDefaults.colors(
                        focusedTextColor = Color(0xFF1E293B),
                        unfocusedTextColor = Color(0xFF1E293B),
                        focusedBorderColor = FyndablePurple,
                        unfocusedBorderColor = Color(0xFFE2E8F0),
                        focusedContainerColor = Color.White,
                        unfocusedContainerColor = Color.White,
                        focusedLabelColor = FyndablePurple,
                        unfocusedLabelColor = Color(0xFF94A3B8),
                    )
                )
                Spacer(Modifier.height(16.dp))

                OutlinedTextField(
                    value = appPassword,
                    onValueChange = { appPassword = it },
                    label = { Text("Application Password") },
                    leadingIcon = { Icon(Icons.Filled.Lock, contentDescription = null, tint = FyndablePurple) },
                    singleLine = true,
                    visualTransformation = PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = androidx.compose.material3.OutlinedTextFieldDefaults.colors(
                        focusedTextColor = Color(0xFF1E293B),
                        unfocusedTextColor = Color(0xFF1E293B),
                        focusedBorderColor = FyndablePurple,
                        unfocusedBorderColor = Color(0xFFE2E8F0),
                        focusedContainerColor = Color.White,
                        unfocusedContainerColor = Color.White,
                        focusedLabelColor = FyndablePurple,
                        unfocusedLabelColor = Color(0xFF94A3B8),
                    )
                )

                error?.let {
                    Spacer(Modifier.height(12.dp))
                    Text(
                        text = it,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.error,
                        textAlign = TextAlign.Center
                    )
                }

                Spacer(Modifier.height(24.dp))

                Button(
                    onClick = {
                        if (username.isBlank() || appPassword.isBlank()) {
                            error = "All fields are required"
                            return@Button
                        }
                        loading = true
                        error = null
                        viewModel.login(username, appPassword) { success, msg ->
                            loading = false
                            if (success) onLoggedIn() else error = msg
                        }
                    },
                    enabled = !loading,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(56.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = FyndablePurple)
                ) {
                    if (loading) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(24.dp),
                            strokeWidth = 2.dp,
                            color = Color.White,
                        )
                    } else {
                        Text("Log in", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }

        Spacer(Modifier.weight(1f))
        
        Text(
            text = "Create an Application Password in WordPress\nunder Users → Profile → Application Passwords.",
            style = MaterialTheme.typography.labelMedium,
            color = Color(0xFF94A3B8),
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(bottom = 32.dp)
        )
    }
}
