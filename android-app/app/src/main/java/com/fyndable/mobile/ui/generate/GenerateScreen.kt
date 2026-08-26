package com.fyndable.mobile.ui.generate

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.ui.ScreenViewModelFactory
import com.fyndable.mobile.ui.components.LoadingState
import com.fyndable.mobile.ui.components.StatusBadge
import com.fyndable.mobile.ui.theme.FyndableBlue

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GenerateScreen(
    authStore: AuthStore,
    viewModel: GenerateViewModel = viewModel(factory = ScreenViewModelFactory(authStore))
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    var keyword by rememberSaveable { mutableStateOf("") }
    var title by rememberSaveable { mutableStateOf("") }
    var wcExpanded by remember { mutableStateOf(false) }
    var wordCount by rememberSaveable { mutableStateOf("1500") }
    var toneExpanded by remember { mutableStateOf(false) }
    var tone by rememberSaveable { mutableStateOf("professional") }
    var includeFaq by rememberSaveable { mutableStateOf(true) }
    var createDraft by rememberSaveable { mutableStateOf(true) }

    Scaffold { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Text("AI Artikel Generator", style = MaterialTheme.typography.titleLarge)

            OutlinedTextField(
                value = keyword,
                onValueChange = { keyword = it },
                label = { Text("Focus keyword") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = title,
                onValueChange = { title = it },
                label = { Text("Titel (optioneel)") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )

            // Word count dropdown
            ExposedDropdownMenuBox(
                expanded = wcExpanded,
                onExpandedChange = { wcExpanded = !wcExpanded }
            ) {
                OutlinedTextField(
                    value = "~${wordCount} woorden",
                    onValueChange = {},
                    readOnly = true,
                    label = { Text("Woordental") },
                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = wcExpanded) },
                    modifier = Modifier.fillMaxWidth().menuAnchor()
                )
                ExposedDropdownMenu(expanded = wcExpanded, onDismissRequest = { wcExpanded = false }) {
                    listOf("500", "1000", "1500", "2000", "3000").forEach { w ->
                        DropdownMenuItem(
                            text = { Text("~$w woorden") },
                            onClick = { wordCount = w; wcExpanded = false }
                        )
                    }
                }
            }

            // Tone dropdown
            ExposedDropdownMenuBox(
                expanded = toneExpanded,
                onExpandedChange = { toneExpanded = !toneExpanded }
            ) {
                OutlinedTextField(
                    value = toneLabels[tone] ?: tone,
                    onValueChange = {},
                    readOnly = true,
                    label = { Text("Tone of voice") },
                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = toneExpanded) },
                    modifier = Modifier.fillMaxWidth().menuAnchor()
                )
                ExposedDropdownMenu(expanded = toneExpanded, onDismissRequest = { toneExpanded = false }) {
                    toneLabels.forEach { (value, label) ->
                        DropdownMenuItem(
                            text = { Text(label) },
                            onClick = { tone = value; toneExpanded = false }
                        )
                    }
                }
            }

            Row(verticalAlignment = Alignment.CenterVertically) {
                Checkbox(checked = includeFaq, onCheckedChange = { includeFaq = it })
                Text("FAQ sectie toevoegen")
            }

            Row(verticalAlignment = Alignment.CenterVertically) {
                Checkbox(checked = createDraft, onCheckedChange = { createDraft = it })
                Text("Opslaan als concept")
            }

            Button(
                onClick = {
                    viewModel.generateArticle(
                        keyword = keyword,
                        title = title,
                        wordCount = wordCount.toIntOrNull() ?: 1500,
                        tone = tone,
                        includeFaq = includeFaq,
                        createDraft = createDraft
                    )
                },
                enabled = state !is GenerateViewModel.UiState.Loading && keyword.isNotBlank(),
                modifier = Modifier.fillMaxWidth()
            ) {
                if (state is GenerateViewModel.UiState.Loading) {
                    CircularProgressIndicator(
                        color = MaterialTheme.colorScheme.onPrimary,
                        strokeWidth = 2.dp,
                        modifier = Modifier.size(20.dp)
                    )
                } else {
                    Text("Artikel genereren")
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            when (val s = state) {
                is GenerateViewModel.UiState.Loading -> {
                    LoadingState(message = "AI schrijft je artikel…\nDit kan 30-60 seconden duren.")
                }
                is GenerateViewModel.UiState.Success -> {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            Text("Resultaat", style = MaterialTheme.typography.titleMedium)
                            s.result.postId?.let {
                                Spacer(modifier = Modifier.height(8.dp))
                                StatusBadge("✓ Opgeslagen als concept (ID: $it)", FyndableBlue)
                            }
                            Spacer(modifier = Modifier.height(12.dp))
                            val content = s.result.content ?: s.result.article ?: s.result.html ?: ""
                            Text(
                                text = content,
                                style = MaterialTheme.typography.bodySmall
                            )
                        }
                    }
                }
                is GenerateViewModel.UiState.Error -> {
                    Card(
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.errorContainer
                        ),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = s.message,
                            color = MaterialTheme.colorScheme.onErrorContainer,
                            modifier = Modifier.padding(16.dp)
                        )
                    }
                }
                is GenerateViewModel.UiState.Idle -> {}
            }
        }
    }
}

private val toneLabels = mapOf(
    "professional" to "Professioneel",
    "casual" to "Casual",
    "friendly" to "Vriendelijk",
    "technical" to "Technisch",
    "authoritative" to "Gezaghebbend",
)
