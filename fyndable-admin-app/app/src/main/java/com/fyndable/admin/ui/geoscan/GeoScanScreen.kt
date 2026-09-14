package com.fyndable.admin.ui.geoscan

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Language
import androidx.compose.material.icons.filled.Link
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.remote.GeoScanReport
import com.fyndable.admin.data.remote.GeoScanSummary
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.GeoScanRepository
import com.fyndable.admin.ui.components.EmptyState
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.OpsHeader
import com.fyndable.admin.ui.components.ScoreGauge
import com.fyndable.admin.ui.components.SectionHeader
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class RecentScansState {
    data object Loading : RecentScansState()
    data class Success(val scans: List<GeoScanSummary>) : RecentScansState()
    data class Error(val message: String) : RecentScansState()
}

@HiltViewModel
class GeoScanViewModel @Inject constructor(
    private val repository: GeoScanRepository,
) : ViewModel() {

    private val _recentState = MutableStateFlow<RecentScansState>(RecentScansState.Loading)
    val recentState = _recentState.asStateFlow()

    var scanning by mutableStateOf(false)
        private set
    var scanError by mutableStateOf<String?>(null)
        private set

    fun loadRecent() {
        _recentState.value = RecentScansState.Loading
        viewModelScope.launch {
            when (val result = repository.recent(30)) {
                is ApiResult.Success -> _recentState.value = RecentScansState.Success(result.data.scans)
                is ApiResult.Error -> _recentState.value = RecentScansState.Error(result.message)
            }
        }
    }

    fun runScan(url: String, keywords: List<String>, language: String, onDone: (GeoScanReport?) -> Unit) {
        scanning = true
        scanError = null
        viewModelScope.launch {
            when (val result = repository.run(url, keywords, language)) {
                is ApiResult.Success -> {
                    scanning = false
                    loadRecent()
                    onDone(result.data.report)
                }
                is ApiResult.Error -> {
                    scanning = false
                    scanError = result.message
                    onDone(null)
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GeoScanScreen(viewModel: GeoScanViewModel = hiltViewModel()) {
    val recentState by viewModel.recentState.collectAsStateWithLifecycle()
    var url by remember { mutableStateOf("https://") }
    var keywords by remember { mutableStateOf("") }
    var language by remember { mutableStateOf("nl") }
    var reportDialog by remember { mutableStateOf<GeoScanReport?>(null) }

    LaunchedEffect(Unit) {
        if (recentState is RecentScansState.Loading) viewModel.loadRecent()
    }

    Scaffold(
        topBar = { OpsHeader(title = "GEO Scan") },
        containerColor = Color(0xFFF8FAFC)
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState()),
        ) {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                border = BorderStroke(1.dp, Color(0xFFF1F5F9))
            ) {
                Column(Modifier.padding(20.dp)) {
                    Text("Quick GEO Scan", fontWeight = FontWeight.Black, fontSize = 18.sp, color = Color(0xFF1E293B))
                    Text("Analyze search visibility for any prospect", style = MaterialTheme.typography.bodyMedium, color = Color(0xFF64748B))
                    Spacer(Modifier.height(20.dp))

                    OutlinedTextField(
                        value = url,
                        onValueChange = { url = it },
                        label = { Text("Prospect URL") },
                        leadingIcon = { Icon(Icons.Filled.Link, contentDescription = null, tint = Color(0xFF7C3AED)) },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = androidx.compose.material3.OutlinedTextFieldDefaults.colors(
                            focusedTextColor = Color(0xFF1E293B),
                            unfocusedTextColor = Color(0xFF1E293B),
                            focusedBorderColor = Color(0xFF7C3AED),
                            unfocusedBorderColor = Color(0xFFE2E8F0),
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White,
                            focusedLabelColor = Color(0xFF7C3AED),
                            unfocusedLabelColor = Color(0xFF94A3B8),
                        )
                    )
                    Spacer(Modifier.height(16.dp))
                    OutlinedTextField(
                        value = keywords,
                        onValueChange = { keywords = it },
                        label = { Text("Target Keywords (one per line)") },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(100.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = androidx.compose.material3.OutlinedTextFieldDefaults.colors(
                            focusedTextColor = Color(0xFF1E293B),
                            unfocusedTextColor = Color(0xFF1E293B),
                            focusedBorderColor = Color(0xFF7C3AED),
                            unfocusedBorderColor = Color(0xFFE2E8F0),
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White,
                            focusedLabelColor = Color(0xFF7C3AED),
                            unfocusedLabelColor = Color(0xFF94A3B8),
                        )
                    )
                    Spacer(Modifier.height(16.dp))
                    
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        LanguageSelector(
                            selected = language,
                            onSelect = { language = it },
                            modifier = Modifier.weight(1f)
                        )
                        // Mock model selector as per guidelines
                        ModelSelector(modifier = Modifier.weight(1f))
                    }
                    
                    Spacer(Modifier.height(20.dp))
                    
                    if (viewModel.scanning) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            LinearProgressIndicator(
                                modifier = Modifier.fillMaxWidth(),
                                color = Color(0xFF7C3AED),
                                trackColor = Color(0xFFEEF2FF)
                            )
                            Spacer(Modifier.height(8.dp))
                            Text("Analyzing search landscape... (30-60s)", fontSize = 12.sp, color = Color(0xFF64748B))
                        }
                    } else {
                        Button(
                            onClick = {
                                val kwList = keywords.lines().map { it.trim() }.filter { it.isNotBlank() }
                                if (url.isNotBlank() && kwList.isNotEmpty()) {
                                    viewModel.runScan(url, kwList, language) { report ->
                                        if (report != null) reportDialog = report
                                    }
                                }
                            },
                            enabled = url.isNotBlank() && keywords.lines().any { it.isNotBlank() },
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(52.dp),
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF7C3AED))
                        ) { Text("Start GEO Scan", fontWeight = FontWeight.Bold) }
                    }
                    
                    viewModel.scanError?.let {
                        Spacer(Modifier.height(12.dp))
                        Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }

            PaddingValues(horizontal = 16.dp).let {
                SectionHeader("Recent Scans", modifier = Modifier.padding(horizontal = 16.dp))
            }

            when (val s = recentState) {
                is RecentScansState.Loading -> LoadingIndicator()
                is RecentScansState.Error -> ErrorView(s.message, onRetry = { viewModel.loadRecent() })
                is RecentScansState.Success -> {
                    if (s.scans.isEmpty()) {
                        EmptyState("No scans yet")
                    } else {
                        Column(Modifier.padding(horizontal = 16.dp)) {
                            s.scans.forEach { scan -> ScanCard(scan) { reportDialog = scan.result } }
                        }
                    }
                }
            }
            Spacer(Modifier.height(32.dp))
        }
    }

    reportDialog?.let { report ->
        ReportDialog(report = report, onDismiss = { reportDialog = null })
    }
}

@Composable
private fun ScanCard(scan: GeoScanSummary, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 6.dp),
        onClick = onClick,
        colors = CardDefaults.cardColors(containerColor = Color.White),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        border = BorderStroke(1.dp, Color(0xFFF1F5F9))
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            ScoreGauge(score = scan.score ?: 0, size = 48.dp)
            Spacer(Modifier.width(16.dp))
            Column(Modifier.weight(1f)) {
                Text(scan.url, fontWeight = FontWeight.Bold, color = Color(0xFF1E293B), maxLines = 1)
                Text(
                    text = scan.keywords.take(40) + (if(scan.keywords.length > 40) "..." else ""),
                    style = MaterialTheme.typography.bodySmall,
                    color = Color(0xFF64748B)
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                val statusText = when {
                    (scan.score ?: 0) >= 80 -> "GREAT"
                    (scan.score ?: 0) >= 50 -> "NEEDS WORK"
                    else -> "POOR"
                }
                val statusColor = when {
                    (scan.score ?: 0) >= 80 -> Color(0xFF10B981)
                    (scan.score ?: 0) >= 50 -> Color(0xFFF59E0B)
                    else -> Color(0xFFEF4444)
                }
                Text(statusText, fontWeight = FontWeight.Black, fontSize = 10.sp, color = statusColor)
                Text(scan.createdAt.split(" ")[0], style = MaterialTheme.typography.labelSmall, color = Color(0xFF94A3B8))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun LanguageSelector(selected: String, onSelect: (String) -> Unit, modifier: Modifier = Modifier) {
    var expanded by remember { mutableStateOf(false) }
    val options = listOf("nl" to "Dutch", "en" to "English", "de" to "German", "fr" to "French")
    val selectedLabel = options.find { it.first == selected }?.second ?: selected

    ExposedDropdownMenuBox(
        expanded = expanded,
        onExpandedChange = { expanded = it },
        modifier = modifier
    ) {
        OutlinedTextField(
            value = selectedLabel,
            onValueChange = {},
            readOnly = true,
            label = { Text("Language") },
            leadingIcon = { Icon(Icons.Filled.Language, contentDescription = null, tint = Color(0xFF7C3AED), modifier = Modifier.size(18.dp)) },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) },
            modifier = Modifier.fillMaxWidth().menuAnchor(),
            shape = RoundedCornerShape(12.dp),
            textStyle = MaterialTheme.typography.bodyMedium,
            colors = androidx.compose.material3.OutlinedTextFieldDefaults.colors(
                focusedTextColor = Color(0xFF1E293B),
                unfocusedTextColor = Color(0xFF1E293B),
                focusedBorderColor = Color(0xFF7C3AED),
                unfocusedBorderColor = Color(0xFFE2E8F0),
                focusedContainerColor = Color.White,
                unfocusedContainerColor = Color.White,
                focusedLabelColor = Color(0xFF7C3AED),
                unfocusedLabelColor = Color(0xFF94A3B8),
            )
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            options.forEach { (code, label) ->
                DropdownMenuItem(
                    text = { Text(label) },
                    onClick = { onSelect(code); expanded = false }
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ModelSelector(modifier: Modifier = Modifier) {
    var expanded by remember { mutableStateOf(false) }
    var selected by remember { mutableStateOf("GPT-4o (Precise)") }

    ExposedDropdownMenuBox(
        expanded = expanded,
        onExpandedChange = { expanded = it },
        modifier = modifier
    ) {
        OutlinedTextField(
            value = selected,
            onValueChange = {},
            readOnly = true,
            label = { Text("AI Model") },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) },
            modifier = Modifier.fillMaxWidth().menuAnchor(),
            shape = RoundedCornerShape(12.dp),
            textStyle = MaterialTheme.typography.bodyMedium,
            colors = androidx.compose.material3.OutlinedTextFieldDefaults.colors(
                focusedTextColor = Color(0xFF1E293B),
                unfocusedTextColor = Color(0xFF1E293B),
                focusedBorderColor = Color(0xFF7C3AED),
                unfocusedBorderColor = Color(0xFFE2E8F0),
                focusedContainerColor = Color.White,
                unfocusedContainerColor = Color.White,
                focusedLabelColor = Color(0xFF7C3AED),
                unfocusedLabelColor = Color(0xFF94A3B8),
            )
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            listOf("GPT-4o (Precise)", "Claude 3.5 Sonnet", "Gemini 1.5 Pro").forEach { model ->
                DropdownMenuItem(
                    text = { Text(model) },
                    onClick = { selected = model; expanded = false }
                )
            }
        }
    }
}

@Composable
private fun ReportDialog(report: GeoScanReport, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Scan Report", fontWeight = FontWeight.Black)
                Spacer(Modifier.weight(1f))
                ScoreGauge(score = report.score, size = 56.dp)
            }
        },
        text = {
            Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                Text(report.url, color = Color(0xFF7C3AED), fontWeight = FontWeight.Bold)
                Text("Scanned on ${report.scannedAt}", style = MaterialTheme.typography.labelMedium, color = Color(0xFF94A3B8))
                
                Spacer(Modifier.height(20.dp))
                
                SectionHeader("Findings")
                report.findings.forEach { finding ->
                    Row(Modifier.padding(vertical = 4.dp)) {
                        Text("• ", fontWeight = FontWeight.Black, color = Color(0xFF7C3AED))
                        Text(finding, style = MaterialTheme.typography.bodyMedium, color = Color(0xFF334155))
                    }
                }

                Spacer(Modifier.height(16.dp))
                SectionHeader("Recommendations")
                report.priorityRankedRecommendations.forEach { rec ->
                    Surface(
                        modifier = Modifier.padding(vertical = 4.dp),
                        shape = RoundedCornerShape(8.dp),
                        color = Color(0xFFF0F9FF),
                        border = BorderStroke(1.dp, Color(0xFFBAE6FD))
                    ) {
                        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.Top) {
                            Checkbox(
                                checked = false, 
                                onCheckedChange = {}, 
                                modifier = Modifier.size(20.dp).padding(top = 2.dp),
                                colors = CheckboxDefaults.colors(uncheckedColor = Color(0xFF0EA5E9))
                            )
                            Spacer(Modifier.width(8.dp))
                            Text(rec, style = MaterialTheme.typography.bodySmall, color = Color(0xFF0369A1))
                        }
                    }
                }
            }
        },
        confirmButton = {
            Button(
                onClick = onDismiss,
                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF7C3AED)),
                shape = RoundedCornerShape(8.dp)
            ) { Text("Done", fontWeight = FontWeight.Bold) }
        },
        shape = RoundedCornerShape(24.dp),
        containerColor = Color.White
    )
}
