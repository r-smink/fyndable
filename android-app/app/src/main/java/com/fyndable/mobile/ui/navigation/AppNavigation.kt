package com.fyndable.mobile.ui.navigation

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Article
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.Hub
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.ui.clusters.ClustersScreen
import com.fyndable.mobile.ui.components.FyndableGradientBackground
import com.fyndable.mobile.ui.generate.GenerateScreen
import com.fyndable.mobile.ui.keywords.KeywordsScreen
import com.fyndable.mobile.ui.login.LoginScreen
import com.fyndable.mobile.ui.performance.PerformanceScreen
import com.fyndable.mobile.ui.posts.PostsScreen

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

@Composable
fun AppNavigation(
    authStore: AuthStore,
    isAuthenticated: Boolean,
) {
    val navController = rememberNavController()

    if (!isAuthenticated) {
        LoginScreen(authStore = authStore)
        return
    }

    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentDestination = navBackStackEntry?.destination

    Scaffold(
        bottomBar = {
            NavigationBar {
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
        FyndableGradientBackground(
            modifier = Modifier.padding(innerPadding)
        ) {
            NavHost(
                navController = navController,
                startDestination = Screen.Keywords.route
            ) {
                composable(Screen.Keywords.route) {
                    KeywordsScreen(authStore = authStore)
                }
                composable(Screen.Clusters.route) {
                    ClustersScreen(authStore = authStore)
                }
                composable(Screen.Generate.route) {
                    GenerateScreen(authStore = authStore)
                }
                composable(Screen.Posts.route) {
                    PostsScreen(authStore = authStore)
                }
                composable(Screen.Performance.route) {
                    PerformanceScreen(authStore = authStore)
                }
            }
        }
    }
}
