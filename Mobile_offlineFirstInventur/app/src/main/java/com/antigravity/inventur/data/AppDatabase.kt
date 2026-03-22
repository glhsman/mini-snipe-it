package com.antigravity.inventur.data

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase

@Database(entities = [Gfgh::class, Asset::class, Sn::class], version = 1)
abstract class AppDatabase : RoomDatabase() {
    abstract fun inventoryDao(): InventoryDao

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "asset.db"
                )
                .createFromAsset("asset.db3")
                .fallbackToDestructiveMigration() // For dev simplicity
                .build()
                INSTANCE = instance
                instance
            }
        }
    }
}
