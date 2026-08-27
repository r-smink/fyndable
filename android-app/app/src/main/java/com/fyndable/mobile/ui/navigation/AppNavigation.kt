package com.fyndable.mobile.ui.navigation

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Article
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.Hub
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.data.store.SiteAccount
import com.fyndable.mobile.ui.ScreenViewModelFactory
import com.fyndable.mobile.ui.clusters.ClustersScreen
import com.fyndable.mobile.ui.components.FyndableGradientBackground
import com.fyndable.mobile.ui.generate.GenerateScreen
import com.fyndable.mobile.ui.keywords.KeywordsScreen
import com.fyndable.mobile.ui.login.LoginScreen
import com.fyndable.mobile.ui.performance.PerformanceScreen
import com.fyndable.mobile.ui.posts.PostsScreen
import kotlinx.coroutines.launch

sealed class Screen(val route: String, val label: String, val icon: ImageVector) {
    data object Keywords : Screen("keywords", "Keywords", Icons.Filled.Search)
    data object Clusters : Screen("clusters", "Clusters", Icons.Filled.Hub)
    data object Generate : Screen("generate", "Genereer", Icons.Filled.AutoAwesome)
    data object Posts : Screen("posts", "Berichten", Icons.Filled.Article)
    data object Performance : Screen("performance", "Prestaties", Icons.Filled.BarChart)
}

private val bottomNavItems = listOf(
    Screen.Keywords,
    Screen.Clusters,
    Screen.Generate,
    Screen.Posts,
    Screen.Performance,
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AppNavigation(
    authStore: AuthStore,
    isAuthenticated: Boolean,
) {
    val navController = rememberNavController()
    val activeSite by authStore.activeSiteFlow.collectAsStateWithLifecycle(initialValue = null)
    val sites by authStore.sitesFlow.collectAsStateWithLifecycle(initialValue = emptyList())
    val scope = rememberCoroutineScope()
    var showAddSite by rememberSaveable { mutableStateOf(false) }
    val siteId = activeSite?.id ?: "none"

    if (!isAuthenticated || activeSite == null) {
        LoginScreen(authStore = authStore)
        return
    }

    if (showAddSite) {
        LoginScreen(
            authStore = authStore,
            onLoginSuccess = { showAddSite = false }
        )
        return
    }

    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentDestination = navBackStackEntry?.destination

    FyndableGradientBackground {
        Scaffold(
            containerColor = Color.Transparent,
            topBar = {
                TopAppBar(
                    title = { Text("Fyndable") },
                    actions = {
                        SiteSelectorButton(
                            activeSite = activeSite,
                            sites = sites,
                            onSiteSelect = { site ->
                                scope.launch { authStore.selectSite(site.id) }
                            },
                            onAddNew = { showAddSite = true }
                        )
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = Color.Transparent,
                        titleContentColor = Color.White,
                        actionIconContentColor = Color.White
                    )
                )
            },
            bottomBar = {
                NavigationBar(
                    containerColor = Color.Transparent,
                    tonalElevation = 0.dp
                ) {
                    bottomNavItems.forEach { screen ->
                        NavigationBarItem(
                            icon = { Icon(screen.icon, contentDescription = screen.label) },
                            label = { Text(screen.label) },
                            selected = currentDestination?.hierarchy?.any { it.route == screen.route } == true,
                            onClick = {
                                navController.navigate(screen.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            }
                        )
                    }
                }
            }
        ) { innerPadding ->
            NavHost(
                navController = navController,
                startDestination = Screen.Keywords.route,
                modifier = Modifier.padding(innerPadding)
            ) {
                composable(Screen.Keywords.route) {
                    val vm: com.fyndable.mobile.ui.keywords.KeywordsViewModel = viewModel(
                        key = "keywords-$siteId",
                        factory = ScreenViewModelFactory(authStore)
                    )
                    KeywordsScreen(authStore = authStore, viewModel = vm)
                }
                composable(Screen.Clusters.route) {
                    val vm: com.fyndable.mobile.ui.clusters.ClustersViewModel = viewModel(
                        key = "clusters-$siteId",
                        factory = ScreenViewModelFactory(authStore)
                    )
                    ClustersScreen(authStore = authStore, viewModel = vm)
                }
                composable(Screen.Generate.route) {
                    val vm: com.fyndable.mobile.ui.generate.GenerateViewModel = viewModel(
                        key = "generate-$siteId",
                        factory = ScreenViewModelFactory(authStore)
                    )
                    GenerateScreen(authStore = authStore, viewModel = vm)
                }
                composable(Screen.Posts.route) {
                    val vm: com.fyndable.mobile.ui.posts.PostsViewModel = viewModel(
                        key = "posts-$siteId",
                        factory = ScreenViewModelFactory(authStore)
                    )
                    PostsScreen(authStore = authStore, viewModel = vm)
                }
                composable(Screen.Performance.route) {
                    val vm: com.fyndable.mobile.ui.performance.PerformanceViewModel = viewModel(
                        key = "performance-$siteId",
                        factory = ScreenViewModelFactory(authStore)
                    )
                    PerformanceScreen(authStore = authStore, viewModel = vm)
                }
            }
        }
    }
}

@Composable
private fun SiteSelectorButton(
    activeSite: SiteAccount?,
    sites: List<SiteAccount>,
    onSiteSelect: (SiteAccount) -> Unit,
    onAddNew: () -> Unit
) {
    var expanded by remember { mutableStateOf(false) }
    TextButton(onClick = { expanded = true }) {
        Text(
            text = activeSite?.label?.takeIf { it.isNotBlank() } ?: activeSite?.siteUrl ?: "Selecteer site",
            color = Color.White,
            style = MaterialTheme.typography.labelLarge
        )
    }
    DropdownMenu(
        expanded = expanded,
        onDismissRequest = { expanded = false }
    ) {
        sites.forEach { site ->
            DropdownMenuItem(
                text = { Text(site.label.takeIf { it.isNotBlank() } ?: site.siteUrl) },
                onClick = {
                    expanded = false
                    onSiteSelect(site)
                }
            )
        }
        if (sites.isNotEmpty()) {
            HorizontalDivider()
        }
        DropdownMenuItem(
            text = { Text("Koppel nieuwe") },
            onClick = {
                expanded = false
                onAddNew()
            }
        )
    }
}
