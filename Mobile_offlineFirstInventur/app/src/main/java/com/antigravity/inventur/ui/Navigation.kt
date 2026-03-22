package com.antigravity.inventur.ui

import androidx.compose.runtime.Composable
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.compose.ui.platform.LocalContext
import com.antigravity.inventur.data.AppDatabase
import com.antigravity.inventur.data.InventoryRepository

sealed class Screen(val route: String) {
    object CompanySelection : Screen("company_selection")
    object Inventory : Screen("inventory")
    object Scanner : Screen("scanner")
}

@Composable
fun InventoryAppNavigation() {
    val navController = rememberNavController()
    val context = LocalContext.current
    val database = AppDatabase.getDatabase(context)
    val repository = InventoryRepository(database.inventoryDao())
    val viewModel: MainViewModel = viewModel(
        factory = MainViewModelFactory(repository)
    )

    NavHost(navController = navController, startDestination = Screen.CompanySelection.route) {
        composable(Screen.CompanySelection.route) {
            CompanySelectionScreen(
                viewModel = viewModel,
                onCompanySelected = {
                    navController.navigate(Screen.Inventory.route)
                }
            )
        }
        composable(Screen.Inventory.route) {
            InventoryScreen(
                viewModel = viewModel,
                onBack = {
                    navController.popBackStack()
                },
                onScanRequested = {
                    navController.navigate(Screen.Scanner.route)
                }
            )
        }
        composable(Screen.Scanner.route) {
            ScannerScreen(
                onBarcodeScanned = { barcode ->
                    viewModel.onSnEntered(barcode)
                    navController.popBackStack()
                }
            )
        }
    }
}
