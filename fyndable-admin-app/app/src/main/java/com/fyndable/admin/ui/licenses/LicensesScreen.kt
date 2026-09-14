package com.fyndable.admin.ui.licenses

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.remote.GenerateLicenseRequest
import com.fyndable.admin.data.remote.License
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.LicenseRepository
import com.fyndable.admin.ui.components.EmptyState
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.FilterPill
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.OpsHeader
import com.fyndable.admin.ui.components.StatusBadge
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class LicensesState {
    data object Loading : LicensesState()
    data class Success(val licenses: List<License>, val total: Int) : LicensesState()
    data class Error(val message: String) : LicensesState()
}

@HiltViewModel
class LicensesViewModel @Inject constructor(
    private val repository: LicenseRepository,
) : ViewModel() {

    private val _state = MutableStateFlow<LicensesState>(LicensesState.Loading)
    val state = _state.asStateFlow()

    var statusFilter by mutableStateOf<String?>(null)
        private set
    var tierFilter by mutableStateOf<String?>(null)
        private set
    var searchQuery by mutableStateOf("")
        private set

    fun setFilters(status: String?, tier: String?, search: String) {
        statusFilter = status?.takeIf { it != "All" && it.isNotBlank() }
        tierFilter = tier?.takeIf { it.isNotBlank() }
        searchQuery = search
        load()
    }

    fun load() {
        _state.value = LicensesState.Loading
        viewModelScope.launch {
            when (val result = repository.list(statusFilter, null, tierFilter, searchQuery.ifBlank { null }, 100, 0)) {
                is ApiResult.Success -> _state.value = LicensesState.Success(result.data.licenses, result.data.total)
                is ApiResult.Error -> _state.value = LicensesState.Error(result.message)
            }
        }
    }

    fun generate(req: GenerateLicenseRequest, onDone: (Boolean, String?) -> Unit) {
        viewModelScope.launch {
            when (val result = repository.generate(req)) {
                is ApiResult.Success -> {
                    load()
                    val keys = result.data.result.licenses?.map { it.licenseKey } ?: listOfNotNull(result.data.result.licenseKey)
                    onDone(true, keys.joinToString("\n"))
                }
                is ApiResult.Error -> onDone(false, result.message)
            }
        }
    }

    fun revoke(key: String, reason: String, onDone: (Boolean, String?) -> Unit) {
        viewModelScope.launch {
            when (val result = repository.revoke(key, reason)) {
                is ApiResult.Success -> { load(); onDone(true, null) }
                is ApiResult.Error -> onDone(false, result.message)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LicensesScreen(viewModel: LicensesViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    var showGenerateDialog by remember { mutableStateOf(false) }
    var revokeTarget by remember { mutableStateOf<License?>(null) }

    Scaffold(
        topBar = {
            OpsHeader(title = "License Keys")
        },
        floatingActionButton = {
            FloatingActionButton(
                onClick = { showGenerateDialog = true },
                containerColor = Color(0xFF8F39AC),
                contentColor = Color.White,
                shape = RoundedCornerShape(16.dp)
            ) {
                Icon(Icons.Filled.Add, contentDescription = "Generate")
            }
        },
        containerColor = Color(0xFFF8FAFC)
    ) { padding ->
        Column(modifier = Modifier.fillMaxSize().padding(padding)) {
            // Filters
            LicenseFiltersRow(
                selectedStatus = viewModel.statusFilter ?: "All",
                onStatusSelected = { viewModel.setFilters(it, viewModel.tierFilter, viewModel.searchQuery) }
            )
            
            OutlinedTextField(
                value = viewModel.searchQuery,
                onValueChange = { viewModel.setFilters(viewModel.statusFilter, viewModel.tierFilter, it) },
                placeholder = { Text("Search by domain or email...") },
                leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null, tint = Color(0xFF94A3B8)) },
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedTextColor = Color(0xFF1E293B),
                    unfocusedTextColor = Color(0xFF1E293B),
                    focusedBorderColor = Color(0xFF8F39AC),
                    unfocusedBorderColor = Color(0xFFE2E8F0),
                    focusedContainerColor = Color.White,
                    unfocusedContainerColor = Color.White
                )
            )

            when (val s = state) {
                is LicensesState.Loading -> LoadingIndicator()
                is LicensesState.Error -> ErrorView(s.message, onRetry = { viewModel.load() })
                is LicensesState.Success -> {
                    if (s.licenses.isEmpty()) {
                        EmptyState("No licenses found")
                    } else {
                        LazyColumn(
                            verticalArrangement = Arrangement.spacedBy(12.dp),
                            contentPadding = PaddingValues(16.dp)
                        ) {
                            items(s.licenses) { license ->
                                LicenseCard(
                                    license = license,
                                    onRevoke = { revokeTarget = license },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    if (showGenerateDialog) {
        GenerateLicenseDialog(
            onDismiss = { showGenerateDialog = false },
            onGenerate = { req ->
                viewModel.generate(req) { success, _ ->
                    showGenerateDialog = false
                }
            },
        )
    }

    revokeTarget?.let { license ->
        RevokeDialog(
            license = license,
            onDismiss = { revokeTarget = null },
            onConfirm = { reason ->
                viewModel.revoke(license.licenseKey, reason) { _, _ -> revokeTarget = null }
            },
        )
    }
}

@Composable
private fun LicenseFiltersRow(
    selectedStatus: String,
    onStatusSelected: (String) -> Unit
) {
    val statuses = listOf("All", "Active", "Used", "Trial", "Expired", "Revoked")
    LazyRow(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp)
    ) {
        items(statuses) { status ->
            FilterPill(
                text = status,
                selected = selectedStatus.equals(status, ignoreCase = true),
                onClick = { onStatusSelected(status) }
            )
        }
    }
}

@Composable
private fun LicenseCard(license: License, onRevoke: () -> Unit) {
    val clipboard = LocalClipboardManager.current
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        border = BorderStroke(1.dp, Color(0xFFF1F5F9))
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = license.assignedTo?.takeIf { it.isNotBlank() } ?: "No Domain Assigned",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF1E293B)
                )
                StatusBadge(license.status)
            }
            
            Spacer(Modifier.height(4.dp))
            Text(
                text = "${license.tier.uppercase()} · ${license.licenseType.uppercase()}",
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                color = Color(0xFF94A3B8),
                letterSpacing = 0.5.sp
            )
            
            Spacer(Modifier.height(16.dp))
            
            Surface(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(8.dp),
                color = Color(0xFFF8FAFC),
                border = BorderStroke(1.dp, Color(0xFFE2E8F0))
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = license.licenseKey,
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color(0xFF475569),
                        maxLines = 1
                    )
                    TextButton(
                        onClick = { clipboard.setText(AnnotatedString(license.licenseKey)) },
                        contentPadding = PaddingValues(horizontal = 8.dp, vertical = 0.dp)
                    ) {
                        Icon(
                            Icons.Filled.ContentCopy,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = Color(0xFF8F39AC)
                        )
                        Spacer(Modifier.width(4.dp))
                        Text("Copy", fontSize = 12.sp, color = Color(0xFF8F39AC))
                    }
                }
            }
            
            if (license.status != "revoked") {
                Spacer(Modifier.height(12.dp))
                Button(
                    onClick = onRevoke,
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFFEE2E2), contentColor = Color(0xFFEF4444)),
                    shape = RoundedCornerShape(8.dp),
                    elevation = null
                ) {
                    Text("Revoke License", fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun GenerateLicenseDialog(
    onDismiss: () -> Unit,
    onGenerate: (GenerateLicenseRequest) -> Unit,
) {
    var tier by remember { mutableStateOf("starter") }
    var assignedTo by remember { mutableStateOf("") }
    var notes by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Generate License Key", fontWeight = FontWeight.Bold) },
        text = {
            Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                DropdownSelector(
                    label = "License Tier",
                    options = listOf("trial", "starter", "early_adopters", "professional", "business", "agency", "dev"),
                    selected = tier,
                    onSelect = { tier = it }
                )
                Spacer(Modifier.height(16.dp))
                OutlinedTextField(
                    value = assignedTo,
                    onValueChange = { assignedTo = it },
                    label = { Text("Customer Email / Domain") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedTextColor = Color(0xFF1E293B),
                        unfocusedTextColor = Color(0xFF1E293B),
                        focusedBorderColor = Color(0xFF8F39AC),
                        unfocusedBorderColor = Color(0xFFE2E8F0),
                        focusedContainerColor = Color.White,
                        unfocusedContainerColor = Color.White
                    )
                )
                Spacer(Modifier.height(16.dp))
                OutlinedTextField(
                    value = notes,
                    onValueChange = { notes = it },
                    label = { Text("Internal Notes") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    minLines = 2,
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedTextColor = Color(0xFF1E293B),
                        unfocusedTextColor = Color(0xFF1E293B),
                        focusedBorderColor = Color(0xFF8F39AC),
                        unfocusedBorderColor = Color(0xFFE2E8F0),
                        focusedContainerColor = Color.White,
                        unfocusedContainerColor = Color.White
                    )
                )
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    onGenerate(
                        GenerateLicenseRequest(
                            count = 1,
                            type = if (tier == "trial") "trial" else "paid",
                            tier = tier,
                            maxSites = 1,
                            assignedTo = assignedTo,
                            notes = notes,
                        )
                    )
                },
                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF8F39AC)),
                shape = RoundedCornerShape(8.dp)
            ) { Text("Generate", fontWeight = FontWeight.Bold) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
        shape = RoundedCornerShape(20.dp),
        containerColor = Color.White
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DropdownSelector(label: String, options: List<String>, selected: String, onSelect: (String) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = it }) {
        OutlinedTextField(
            value = selected.replaceFirstChar { it.uppercase() },
            onValueChange = {},
            readOnly = true,
            label = { Text(label) },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) },
            modifier = Modifier.fillMaxWidth().menuAnchor(),
            shape = RoundedCornerShape(8.dp),
            colors = OutlinedTextFieldDefaults.colors(
                focusedTextColor = Color(0xFF1E293B),
                unfocusedTextColor = Color(0xFF1E293B),
                focusedBorderColor = Color(0xFF8F39AC),
                unfocusedBorderColor = Color(0xFFE2E8F0),
                focusedContainerColor = Color.White,
                unfocusedContainerColor = Color.White
            )
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            options.forEach { opt ->
                DropdownMenuItem(text = { Text(opt.replaceFirstChar { it.uppercase() }) }, onClick = { onSelect(opt); expanded = false })
            }
        }
    }
}

@Composable
private fun RevokeDialog(license: License, onDismiss: () -> Unit, onConfirm: (String) -> Unit) {
    var reason by remember { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Revoke License", color = Color(0xFFEF4444)) },
        text = {
            Column {
                Text("Are you sure you want to revoke the license for:")
                Text(license.assignedTo ?: license.licenseKey, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(16.dp))
                OutlinedTextField(
                    value = reason,
                    onValueChange = { reason = it },
                    label = { Text("Reason for revocation") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedTextColor = Color(0xFF1E293B),
                        unfocusedTextColor = Color(0xFF1E293B),
                        focusedBorderColor = Color(0xFF8F39AC),
                        unfocusedBorderColor = Color(0xFFE2E8F0),
                        focusedContainerColor = Color.White,
                        unfocusedContainerColor = Color.White
                    )
                )
            }
        },
        confirmButton = {
            Button(
                onClick = { onConfirm(reason.ifBlank { "Revoked via admin app" }) },
                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444)),
                shape = RoundedCornerShape(8.dp)
            ) { Text("Revoke Now", fontWeight = FontWeight.Bold) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
        shape = RoundedCornerShape(20.dp),
        containerColor = Color.White
    )
}
