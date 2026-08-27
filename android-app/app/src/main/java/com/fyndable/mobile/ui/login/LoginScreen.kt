package com.fyndable.mobile.ui.login

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.ui.components.FyndableGradientBackground
import com.fyndable.mobile.ui.components.FyndableGradientButton
import com.fyndable.mobile.ui.components.FyndableLogo
import com.fyndable.mobile.ui.theme.FyndableInk
import com.fyndable.mobile.ui.theme.FyndablePurple
import com.fyndable.mobile.ui.theme.Gray200
import com.fyndable.mobile.ui.theme.Gray500
import com.fyndable.mobile.ui.theme.Gray900
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanIntentResult
import com.journeyapps.barcodescanner.ScanOptions

@Composable
fun LoginScreen(
    authStore: AuthStore,
    onLoginSuccess: () -> Unit = {},
    viewModel: LoginViewModel = viewModel(factory = LoginViewModelFactory(authStore))
) {
    var username by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var siteUrl by rememberSaveable { mutableStateOf("") }
    val state by viewModel.state.collectAsState()

    LaunchedEffect(state) {
        if (state is LoginViewModel.LoginState.Success) {
            onLoginSuccess()
        }
    }

    // QR scanner launcher using ZXing
    val qrLauncher = rememberLauncherForActivityResult(
        contract = ScanContract()
    ) { result: ScanIntentResult ->
        val contents = result.contents
        if (!contents.isNullOrBlank()) {
            viewModel.loginWithQr(contents)
        }
    }

    fun launchQrScanner() {
        val options = ScanOptions().apply {
            setDesiredBarcodeFormats(ScanOptions.QR_CODE)
            setPrompt("Scan de Fyndable QR-code")
            setBeepEnabled(true)
            setOrientationLocked(false)
        }
        qrLauncher.launch(options)
    }

    FyndableGradientBackground(
        modifier = Modifier.fillMaxSize()
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = Color.White.copy(alpha = 0.97f),
                shadowElevation = 16.dp,
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier.padding(horizontal = 22.dp, vertical = 28.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    // Logo
                    FyndableLogo(size = 56)
                    Spacer(modifier = Modifier.height(14.dp))
                    Text(
                        text = "Fyndable Smart SEO",
                        style = MaterialTheme.typography.headlineSmall,
                        color = Gray900
                    )
                    Text(
                        text = "SEO on the go",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Gray500
                    )

                    Spacer(modifier = Modifier.height(24.dp))

                    // Error
                    if (state is LoginViewModel.LoginState.Error) {
                        Surface(
                            color = MaterialTheme.colorScheme.errorContainer,
                            shape = MaterialTheme.shapes.small,
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text(
                                text = (state as LoginViewModel.LoginState.Error).message,
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onErrorContainer,
                                modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)
                            )
                        }
                        Spacer(modifier = Modifier.height(16.dp))
                    }

                    // QR scan button — primary action
                    FyndableGradientButton(
                        onClick = { launchQrScanner() },
                        enabled = state !is LoginViewModel.LoginState.Loading,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        if (state is LoginViewModel.LoginState.Loading) {
                            CircularProgressIndicator(
                                color = Color.White,
                                strokeWidth = 2.dp,
                                modifier = Modifier.size(20.dp)
                            )
                        } else {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Filled.QrCodeScanner, contentDescription = null, tint = Color.White)
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    "Scan QR-code",
                                    color = Color.White,
                                    fontWeight = FontWeight.SemiBold,
                                    fontSize = 14.sp
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    // Divider
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        HorizontalDivider(modifier = Modifier.weight(1f))
                        Text(
                            text = "of handmatig",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 12.dp)
                        )
                        HorizontalDivider(modifier = Modifier.weight(1f))
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    // Site URL
                    OutlinedTextField(
                        value = siteUrl,
                        onValueChange = { siteUrl = it },
                        label = { Text("WordPress site URL") },
                        placeholder = { Text("https://jouw-site.nl") },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Uri,
                            imeAction = ImeAction.Next
                        ),
                        shape = RoundedCornerShape(6.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White,
                            focusedBorderColor = FyndablePurple,
                            unfocusedBorderColor = Gray200,
                            focusedTextColor = FyndableInk,
                            unfocusedTextColor = FyndableInk
                        ),
                        modifier = Modifier.fillMaxWidth()
                    )

                    Spacer(modifier = Modifier.height(12.dp))

                    // Username
                    OutlinedTextField(
                        value = username,
                        onValueChange = { username = it },
                        label = { Text("Gebruikersnaam") },
                        placeholder = { Text("bijv. admin") },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(
                            imeAction = ImeAction.Next
                        ),
                        shape = RoundedCornerShape(6.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White,
                            focusedBorderColor = FyndablePurple,
                            unfocusedBorderColor = Gray200,
                            focusedTextColor = FyndableInk,
                            unfocusedTextColor = FyndableInk
                        ),
                        modifier = Modifier.fillMaxWidth()
                    )

                    Spacer(modifier = Modifier.height(12.dp))

                    // Password
                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it },
                        label = { Text("Application Password") },
                        placeholder = { Text("xxxx xxxx xxxx xxxx xxxx xxxx") },
                        singleLine = true,
                        visualTransformation = PasswordVisualTransformation(),
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Password,
                            imeAction = ImeAction.Done
                        ),
                        shape = RoundedCornerShape(6.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White,
                            focusedBorderColor = FyndablePurple,
                            unfocusedBorderColor = Gray200,
                            focusedTextColor = FyndableInk,
                            unfocusedTextColor = FyndableInk
                        ),
                        modifier = Modifier.fillMaxWidth()
                    )

                    Spacer(modifier = Modifier.height(20.dp))

                    // Manual login button
                    OutlinedButton(
                        onClick = { viewModel.login(username, password, siteUrl) },
                        enabled = state !is LoginViewModel.LoginState.Loading,
                        modifier = Modifier.fillMaxWidth(),
                        border = BorderStroke(2.dp, FyndablePurple),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = FyndablePurple)
                    ) {
                        Text("Handmatig inloggen", fontWeight = FontWeight.SemiBold)
                    }

                    Spacer(modifier = Modifier.height(20.dp))

                    Text(
                        text = "Scan de QR-code vanuit WordPress Admin → Fyndable → Connection\nOf maak handmatig een Application Password aan via Gebruikers → Profiel",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray500,
                        textAlign = TextAlign.Center
                    )
                }
            }
        }
    }
}
